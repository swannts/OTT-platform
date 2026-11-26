<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaymentDispute;
use App\Models\PaymentProviderEvent;
use App\Models\RefundRequest;
use App\Models\VerifiedPayment;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Frontend\Models\PayPerView;
use Modules\Subscriptions\Models\Subscription;
use Stripe\StripeClient;

class AdminFinanceController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.payments.view'), 403);

        return response()->json(['data' => [
            'verified_payments' => VerifiedPayment::query()->whereNotNull('fulfilled_at')->count(),
            'initiated_payments' => VerifiedPayment::query()->whereNull('fulfilled_at')->count(),
            'pending_refunds' => RefundRequest::query()->whereIn('status', ['pending', 'approved', 'processing'])->count(),
            'open_disputes' => PaymentDispute::query()->whereNotIn('status', ['won', 'lost', 'closed'])->count(),
            'recent_refunds' => RefundRequest::query()->where('status', 'succeeded')->where('executed_at', '>=', now()->subDays(30))->count(),
        ]]);
    }

    public function payments(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.payments.view'), 403);
        $request->validate([
            'search' => ['sometimes', 'string', 'max:191'],
            'gateway' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', 'string', 'in:paid,initiated,failed,pending'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->paymentFeedQuery();
        if ($request->filled('gateway')) $query->where('gateway', strtolower($request->string('gateway')));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from')->toDateString());
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to')->toDateString());
        if ($request->filled('search')) {
            $needle = '%'.trim($request->string('search')->toString()).'%';
            $query->where(fn (Builder $builder) => $builder->where('provider_reference', 'like', $needle)
                ->orWhere('user_email', 'like', $needle)->orWhere('user_name', 'like', $needle)
                ->orWhere('user_id', 'like', $needle));
        }

        $page = $query->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 25), 1), 100));
        $page->getCollection()->transform(fn (object $row): array => $this->presentPayment($row));
        return response()->json($page);
    }

    public function paymentDetail(Request $request, VerifiedPayment $payment): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.payments.view'), 403);
        $payment->load('user');
        return response()->json(['data' => [
            'payment' => $this->presentVerifiedPayment($payment),
            'refunds' => RefundRequest::query()->where('verified_payment_id', $payment->id)->with(['requester:id,first_name,last_name,email', 'reviewer:id,first_name,last_name,email'])->latest()->get(),
            'disputes' => PaymentDispute::query()->where('verified_payment_id', $payment->id)->latest()->get(),
        ]]);
    }

    public function invoice(Request $request, VerifiedPayment $payment)
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.invoices.view'), 403);
        abort_unless($payment->fulfilled_at && $payment->fulfillment_type, 404);

        if ($payment->fulfillment_type === 'subscription') {
            $data = Subscription::query()->with(['plan', 'subscription_transaction', 'user'])->findOrFail($payment->fulfillment_id);
            return Pdf::loadView('frontend::components.partials.invoice', compact('data'))
                ->setOptions(['defaultFont' => 'Noto Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false])
                ->download('invoice-'.$payment->id.'.pdf');
        }

        abort_unless($payment->fulfillment_type === 'pay_per_view', 404);
        $ppv = PayPerView::query()->with(['movie', 'episode', 'video', 'user', 'PayperviewTransaction'])->findOrFail($payment->fulfillment_id);
        return Pdf::loadView('frontend::components.partials.pay-per-view', compact('ppv'))
            ->setOptions(['defaultFont' => 'Noto Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false])
            ->download('receipt-'.$payment->id.'.pdf');
    }

    public function reconcilePayment(Request $request, VerifiedPayment $payment, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.reconciliation.view'), 403);
        abort_unless($payment->fulfilled_at, 422, 'Only fulfilled payments can be reconciled.');
        try {
            [$providerStatus, $providerAmount, $providerCurrency, $providerRefunded] = $this->fetchProviderPayment($payment);
            $statusMatches = $this->providerPaymentSucceeded($payment->gateway, $providerStatus);
            $amountMatches = $providerAmount === (int) $payment->amount_minor;
            $currencyMatches = strtoupper($providerCurrency) === strtoupper($payment->currency);
            $localRefunded = (int) RefundRequest::query()->where('verified_payment_id', $payment->id)
                ->whereIn('status', ['processing', 'succeeded'])->sum('amount_minor');
            $refundMatches = $providerRefunded === null ? null : $providerRefunded === $localRefunded;
            $result = $statusMatches && $amountMatches && $currencyMatches && ($refundMatches ?? true) ? 'matched' : 'mismatch';
            $runId = DB::table('payment_reconciliation_runs')->insertGetId([
                'verified_payment_id' => $payment->id, 'performed_by' => $request->user()->id,
                'provider_status' => substr($providerStatus, 0, 32), 'provider_amount_minor' => $providerAmount,
                'provider_currency' => strtoupper(substr($providerCurrency, 0, 3)),
                'provider_refunded_minor' => $providerRefunded, 'local_refunded_minor' => $localRefunded,
                'amount_matches' => $amountMatches, 'currency_matches' => $currencyMatches,
                'status_matches' => $statusMatches, 'refunds_match' => $refundMatches, 'result' => $result,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $audit->record($request->user(), 'finance.payment.reconciled', VerifiedPayment::class, $payment->id, null, [
                'run_id' => $runId, 'result' => $result, 'refunds_match' => $refundMatches,
            ], $result === 'matched' ? 'success' : 'failure');
            return response()->json(['data' => [
                'run_id' => $runId, 'result' => $result, 'provider_status' => $providerStatus,
                'provider_amount_minor' => $providerAmount, 'provider_currency' => strtoupper($providerCurrency),
                'local_amount_minor' => (int) $payment->amount_minor, 'local_currency' => strtoupper($payment->currency),
                'refunds_match' => $refundMatches, 'local_refunded_minor' => $localRefunded,
            ]]);
        } catch (\Throwable $exception) {
            Log::warning('Provider payment reconciliation failed.', ['payment_id' => $payment->id, 'gateway' => $payment->gateway, 'exception' => $exception::class]);
            $runId = DB::table('payment_reconciliation_runs')->insertGetId([
                'verified_payment_id' => $payment->id, 'performed_by' => $request->user()->id,
                'result' => 'unavailable', 'failure_code' => $exception::class,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['data' => ['run_id' => $runId, 'result' => 'unavailable', 'message' => 'Provider reconciliation could not be completed.']], 202);
        }
    }

    public function refunds(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.payments.view'), 403);
        $request->validate(['status' => ['sometimes', 'string', 'in:pending,approved,processing,succeeded,failed,rejected'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query = RefundRequest::query()->with(['payment.user:id,first_name,last_name,email', 'requester:id,first_name,last_name,email', 'reviewer:id,first_name,last_name,email'])->latest('id');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return response()->json($query->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function requestRefund(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.refunds.request'), 403);
        $data = $request->validate([
            'verified_payment_id' => ['required', 'integer', 'exists:verified_payments,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $refund = DB::transaction(function () use ($request, $data, $audit): RefundRequest {
            $existing = RefundRequest::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->requested_by === (int) $request->user()->id, 409, 'This idempotency key belongs to another refund request.');
                abort_unless((int) $existing->verified_payment_id === (int) $data['verified_payment_id']
                    && (int) $existing->amount_minor === (int) $data['amount_minor'], 409, 'Idempotency key was reused with different refund details.');
                return $existing;
            }
            $payment = VerifiedPayment::query()->lockForUpdate()->findOrFail($data['verified_payment_id']);
            abort_unless($payment->fulfilled_at && $payment->fulfillment_type, 422, 'Only a verified and fulfilled payment can be refunded.');
            abort_unless(in_array($payment->gateway, ['stripe', 'razorpay', 'paystack'], true), 422, 'Refunds are not supported for this payment provider.');

            $reserved = RefundRequest::query()->where('verified_payment_id', $payment->id)
                ->whereIn('status', ['pending', 'approved', 'processing', 'succeeded'])->sum('amount_minor');
            abort_if($reserved + (int) $data['amount_minor'] > (int) $payment->amount_minor, 422, 'Refund amount exceeds the remaining refundable balance.');

            $refund = RefundRequest::query()->create([
                'verified_payment_id' => $payment->id, 'amount_minor' => $data['amount_minor'],
                'currency' => strtoupper($payment->currency), 'reason' => trim($data['reason']),
                'status' => 'pending', 'idempotency_key' => $data['idempotency_key'],
                'requested_by' => $request->user()->id,
            ]);
            $audit->record($request->user(), 'finance.refund.requested', RefundRequest::class, $refund->id, null, $refund->only(['verified_payment_id', 'amount_minor', 'currency', 'status']));
            return $refund;
        });

        return response()->json(['data' => $refund->load('payment'), 'message' => 'Refund request is awaiting an independent approval.'], 201);
    }

    public function reviewRefund(Request $request, RefundRequest $refund, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.refunds.approve'), 403);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $updated = DB::transaction(function () use ($request, $refund, $data, $audit): RefundRequest {
            $locked = RefundRequest::query()->lockForUpdate()->findOrFail($refund->id);
            abort_if((int) $locked->requested_by === (int) $request->user()->id, 403, 'Refund requests must be reviewed by another staff member.');
            abort_unless($locked->status === 'pending', 409, 'Only pending refund requests can be reviewed.');
            $before = $locked->only(['status', 'reviewed_by']);
            $locked->forceFill([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'failure_message' => $data['decision'] === 'reject' ? ($data['review_note'] ?? 'Rejected by reviewer.') : null,
            ])->save();
            $action = $data['decision'] === 'approve' ? 'finance.refund.approved' : 'finance.refund.rejected';
            $audit->record($request->user(), $action, RefundRequest::class, $locked->id, $before, $locked->only(['status', 'reviewed_by', 'reviewed_at']));
            return $locked;
        });
        return response()->json(['data' => $updated]);
    }

    public function executeRefund(Request $request, RefundRequest $refund, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.refunds.approve'), 403);
        $refund = DB::transaction(function () use ($refund): RefundRequest {
            $locked = RefundRequest::query()->with('payment')->lockForUpdate()->findOrFail($refund->id);
            abort_unless(in_array($locked->status, ['approved', 'processing'], true), 409, 'Only approved refunds can be executed.');
            $locked->forceFill(['status' => 'processing', 'failure_code' => null, 'failure_message' => null])->save();
            return $locked;
        });

        try {
            $result = $this->createProviderRefund($refund);
            $status = $this->normalizeRefundStatus($refund->payment->gateway, $result['status'] ?? 'pending');
            $refund->forceFill([
                'status' => $status,
                'provider_refund_id' => $result['id'] ?? null,
                'executed_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ])->save();
            $audit->record($request->user(), 'finance.refund.executed', RefundRequest::class, $refund->id, ['status' => 'approved'], ['status' => $status, 'provider_refund_id' => $refund->provider_refund_id]);
            return response()->json(['data' => $refund->fresh(), 'message' => $status === 'succeeded' ? 'Refund completed.' : 'Refund submitted to the provider.'], $status === 'succeeded' ? 200 : 202);
        } catch (\Throwable $exception) {
            Log::warning('Provider refund execution needs reconciliation.', ['refund_id' => $refund->id, 'gateway' => $refund->payment->gateway, 'exception' => $exception::class]);
            $audit->record($request->user(), 'finance.refund.execution_uncertain', RefundRequest::class, $refund->id, ['status' => 'approved'], ['status' => 'processing'], 'failure');
            return response()->json(['data' => $refund->fresh(), 'message' => 'The provider response is uncertain. Retry uses the same idempotency key and will not create a duplicate refund.'], 202);
        }
    }

    public function disputes(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.payments.view'), 403);
        return response()->json(PaymentDispute::query()->with('payment.user:id,first_name,last_name,email')->latest('updated_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function failedProviderEvents(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.payments.view'), 403);
        return response()->json(['data' => PaymentProviderEvent::query()
            ->whereIn('status', ['failed', 'received'])
            ->latest('updated_at')->limit(50)->get([
                'id', 'gateway', 'event_id', 'event_type', 'provider_reference',
                'status', 'attempts', 'failure_code', 'updated_at',
            ])]);
    }

    public function reconciliation(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.reconciliation.view'), 403);
        $verifiedMissing = VerifiedPayment::query()->whereNotNull('fulfilled_at')->where(function ($query): void {
            $query->where(function ($subscription): void {
                $subscription->where('purchase_type', 'subscription')->whereNotExists(function ($ledger): void {
                    $ledger->selectRaw('1')->from('subscriptions_transactions as t')
                        ->whereColumn('t.user_id', 'verified_payments.user_id')->whereColumn('t.transaction_id', 'verified_payments.provider_reference')
                        ->whereColumn('t.payment_type', 'verified_payments.gateway');
                });
            })->orWhere(function ($ppv): void {
                $ppv->where('purchase_type', 'pay_per_view')->whereNotExists(function ($ledger): void {
                    $ledger->selectRaw('1')->from('payperviewstransactions as t')
                        ->whereColumn('t.user_id', 'verified_payments.user_id')->whereColumn('t.transaction_id', 'verified_payments.provider_reference')
                        ->whereColumn('t.payment_type', 'verified_payments.gateway');
                });
            });
        });
        $refundBalances = RefundRequest::query()->select('verified_payment_id', DB::raw('SUM(amount_minor) as refund_total'))
            ->whereIn('status', ['processing', 'succeeded'])->groupBy('verified_payment_id')->get();
        return response()->json(['data' => [
            'verified_fulfillments_missing_ledger' => $verifiedMissing->count(),
            'refund_amounts_by_currency' => RefundRequest::query()->select('currency', DB::raw('SUM(amount_minor) as amount_minor'), DB::raw('COUNT(*) as refunds'))
                ->whereIn('status', ['processing', 'succeeded'])->groupBy('currency')->get(),
            'open_disputes' => PaymentDispute::query()->whereNotIn('status', ['won', 'lost', 'closed'])->count(),
            'refund_balance_rows' => $refundBalances->count(),
        ]]);
    }

    public function reconciliationHistory(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.reconciliation.view'), 403);
        $runs = DB::table('payment_reconciliation_runs as r')
            ->join('verified_payments as p', 'p.id', '=', 'r.verified_payment_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.performed_by')
            ->latest('r.id')->limit(50)
            ->get([
                'r.id', 'r.verified_payment_id', 'p.gateway', 'p.provider_reference',
                'r.provider_status', 'r.provider_amount_minor', 'r.provider_currency',
                'r.provider_refunded_minor', 'r.local_refunded_minor', 'r.refunds_match',
                'r.amount_matches', 'r.currency_matches', 'r.status_matches', 'r.result',
                'r.failure_code', 'r.created_at', 'u.first_name as performer_first_name',
                'u.last_name as performer_last_name', 'u.email as performer_email',
            ]);
        return response()->json(['data' => $runs]);
    }

    public function report(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.reconciliation.view'), 403);
        $request->validate(['from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', 'after_or_equal:from']]);
        $payments = VerifiedPayment::query()->whereNotNull('fulfilled_at')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('fulfilled_at', '>=', $request->date('from')->toDateString()))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('fulfilled_at', '<=', $request->date('to')->toDateString()))
            ->selectRaw('DATE(fulfilled_at) as settlement_day, gateway, currency, COUNT(*) as paid_count, SUM(amount_minor) as gross_minor')
            ->groupBy('settlement_day', 'gateway', 'currency')->orderByDesc('settlement_day')->get();
        $refunds = RefundRequest::query()->whereIn('status', ['processing', 'succeeded'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('executed_at', '>=', $request->date('from')->toDateString()))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('executed_at', '<=', $request->date('to')->toDateString()))
            ->selectRaw('DATE(executed_at) as settlement_day, currency, COUNT(*) as refund_count, SUM(amount_minor) as refund_minor')
            ->groupBy('settlement_day', 'currency')->orderByDesc('settlement_day')->get();
        return response()->json(['data' => ['payments' => $payments, 'refunds' => $refunds]]);
    }

    public function export(Request $request)
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'finance.reports.export'), 403);
        $request->validate(['search' => ['sometimes', 'string', 'max:191'], 'gateway' => ['sometimes', 'string', 'max:32'], 'status' => ['sometimes', 'string', 'in:paid,initiated,failed,pending']]);
        $query = $this->paymentFeedQuery();
        if ($request->filled('gateway')) $query->where('gateway', strtolower($request->string('gateway')));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('search')) {
            $needle = '%'.trim($request->string('search')->toString()).'%';
            $query->where(fn (Builder $builder) => $builder->where('provider_reference', 'like', $needle)->orWhere('user_email', 'like', $needle)->orWhere('user_name', 'like', $needle));
        }
        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['source', 'reference', 'gateway', 'user_id', 'customer', 'email', 'purchase_type', 'status', 'amount', 'currency', 'created_at']);
            foreach ($query->orderBy('created_at')->cursor() as $row) {
                $payment = $this->presentPayment($row);
                fputcsv($stream, [$payment['source'], $payment['provider_reference'], $payment['gateway'], $payment['user_id'], $payment['user_name'], $payment['user_email'], $payment['purchase_type'], $payment['status'], $payment['amount'], $payment['currency'], $payment['created_at']]);
            }
            fclose($stream);
        }, 'finance-payments-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function paymentFeedQuery(): Builder
    {
        $verified = DB::table('verified_payments as p')->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->selectRaw("'verified' as source, CAST(p.id AS CHAR) as source_id, p.id as verified_payment_id, p.gateway, p.provider_reference, p.user_id, p.purchase_type, p.purchase_id, p.amount_minor, NULL as amount_major, p.currency, CASE WHEN p.fulfilled_at IS NULL THEN 'initiated' ELSE 'paid' END as status, p.fulfillment_type, p.fulfillment_id, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as user_name, u.email as user_email, p.created_at");

        $legacySubscriptions = DB::table('subscriptions_transactions as t')->join('subscriptions as s', 's.id', '=', 't.subscriptions_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')->whereNull('t.deleted_at')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('verified_payments as v')->whereColumn('v.gateway', 't.payment_type')->whereColumn('v.provider_reference', 't.transaction_id')->whereColumn('v.user_id', 't.user_id'))
            ->selectRaw("'legacy_subscription' as source, CAST(t.id AS CHAR) as source_id, NULL as verified_payment_id, t.payment_type as gateway, t.transaction_id as provider_reference, t.user_id, 'subscription' as purchase_type, s.plan_id as purchase_id, NULL as amount_minor, t.amount as amount_major, t.currency_code as currency, COALESCE(t.payment_status, 'unknown') as status, 'subscription' as fulfillment_type, s.id as fulfillment_id, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as user_name, u.email as user_email, t.created_at");

        $legacyPpv = DB::table('payperviewstransactions as t')->join('pay_per_views as ppv', 'ppv.id', '=', 't.pay_per_view_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('verified_payments as v')->whereColumn('v.gateway', 't.payment_type')->whereColumn('v.provider_reference', 't.transaction_id')->whereColumn('v.user_id', 't.user_id'))
            ->selectRaw("'legacy_pay_per_view' as source, CAST(t.id AS CHAR) as source_id, NULL as verified_payment_id, t.payment_type as gateway, t.transaction_id as provider_reference, t.user_id, 'pay_per_view' as purchase_type, ppv.id as purchase_id, NULL as amount_minor, t.amount as amount_major, NULL as currency, COALESCE(t.payment_status, 'unknown') as status, 'pay_per_view' as fulfillment_type, ppv.id as fulfillment_id, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as user_name, u.email as user_email, t.created_at");

        return DB::query()->fromSub($verified->unionAll($legacySubscriptions)->unionAll($legacyPpv), 'payments')->select('*');
    }

    private function presentPayment(object $row): array
    {
        return [
            'source' => $row->source, 'source_id' => $row->source_id,
            'verified_payment_id' => $row->verified_payment_id ? (int) $row->verified_payment_id : null,
            'gateway' => $row->gateway, 'provider_reference' => $row->provider_reference,
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'user_name' => $row->user_name, 'user_email' => $row->user_email,
            'purchase_type' => $row->purchase_type, 'purchase_id' => $row->purchase_id ? (int) $row->purchase_id : null,
            'status' => strtolower((string) $row->status),
            'amount' => $row->amount_minor !== null ? round((int) $row->amount_minor / $this->currencyMultiplier((string) $row->currency), 2) : ($row->amount_major === null ? null : (float) $row->amount_major),
            'currency' => $row->currency ?: 'UNRECORDED',
            'refundable' => $row->verified_payment_id !== null && $row->status === 'paid' && in_array($row->gateway, ['stripe', 'razorpay', 'paystack'], true),
            'created_at' => $row->created_at,
            'fulfillment_type' => $row->fulfillment_type, 'fulfillment_id' => $row->fulfillment_id,
        ];
    }

    private function presentVerifiedPayment(VerifiedPayment $payment): array
    {
        return [
            'id' => $payment->id, 'gateway' => $payment->gateway, 'provider_reference' => $payment->provider_reference,
            'user' => $payment->user?->only(['id', 'first_name', 'last_name', 'full_name', 'email']), 'purchase_type' => $payment->purchase_type,
            'purchase_id' => $payment->purchase_id, 'amount_minor' => (int) $payment->amount_minor,
            'amount' => round((int) $payment->amount_minor / $this->currencyMultiplier($payment->currency), 2),
            'currency' => $payment->currency, 'status' => $payment->fulfilled_at ? 'paid' : 'initiated',
            'fulfilled_at' => $payment->fulfilled_at, 'created_at' => $payment->created_at,
        ];
    }

    private function createProviderRefund(RefundRequest $refund): array
    {
        $payment = $refund->payment;
        $key = 'refund-'.$refund->idempotency_key;
        if ($payment->gateway === 'stripe') {
            $secret = (string) GetpaymentMethod('stripe_secretkey');
            abort_if($secret === '', 503, 'Stripe refunds are not configured.');
            $result = (new StripeClient($secret))->refunds->create([
                'payment_intent' => $payment->provider_reference,
                'amount' => (int) $refund->amount_minor,
                'metadata' => ['refund_request_id' => (string) $refund->id],
            ], ['idempotency_key' => $key]);
            return ['id' => $result->id, 'status' => $result->status];
        }

        if ($payment->gateway === 'razorpay') {
            $keyId = (string) GetpaymentMethod('razorpay_publickey');
            $secret = (string) GetpaymentMethod('razorpay_secretkey');
            abort_if($keyId === '' || $secret === '', 503, 'Razorpay refunds are not configured.');
            $response = Http::withBasicAuth($keyId, $secret)->withHeaders(['X-Refund-Idempotency' => $key])
                ->post('https://api.razorpay.com/v1/payments/'.rawurlencode($payment->provider_reference).'/refund', [
                    'amount' => (int) $refund->amount_minor,
                    'notes' => ['refund_request_id' => (string) $refund->id],
                ]);
            if (!$response->successful()) throw new \RuntimeException('Razorpay did not confirm the refund request.');
            $data = $response->json();
            return ['id' => $data['id'] ?? null, 'status' => $data['status'] ?? 'pending'];
        }

        $secret = (string) GetpaymentMethod('paystack_secretkey');
        abort_if($secret === '', 503, 'Paystack refunds are not configured.');
        $response = Http::withToken($secret)->post('https://api.paystack.co/refund', [
            'transaction' => $payment->provider_reference,
            'amount' => (int) $refund->amount_minor,
            'currency' => strtoupper($payment->currency),
            'reference' => $key,
            'merchant_note' => 'Approved OTT refund request '.$refund->id,
        ]);
        if ($response->status() === 409) {
            $existing = Http::withToken($secret)->get('https://api.paystack.co/refund', ['reference' => $key]);
            if (!$existing->successful() || !($existing->json('status') ?? false)) throw new \RuntimeException('Existing Paystack refund status could not be confirmed.');
            $data = $existing->json('data.0', []);
        } else {
            if (!$response->successful() || !($response->json('status') ?? false)) throw new \RuntimeException('Paystack did not confirm the refund request.');
            $data = $response->json('data', []);
        }
        return ['id' => isset($data['id']) ? (string) $data['id'] : null, 'status' => $data['status'] ?? 'pending'];
    }

    private function fetchProviderPayment(VerifiedPayment $payment): array
    {
        if ($payment->gateway === 'stripe') {
            $stripe = new StripeClient((string) GetpaymentMethod('stripe_secretkey'));
            $intent = $stripe->paymentIntents->retrieve($payment->provider_reference, ['expand' => ['latest_charge']]);
            $latestCharge = $intent->latest_charge;
            $refundAmount = is_object($latestCharge) ? (int) ($latestCharge->amount_refunded ?? 0) : null;
            return [$intent->status, (int) ($intent->amount_received ?: $intent->amount), strtoupper((string) $intent->currency), $refundAmount];
        }
        if ($payment->gateway === 'razorpay') {
            $api = new \Razorpay\Api\Api((string) GetpaymentMethod('razorpay_publickey'), (string) GetpaymentMethod('razorpay_secretkey'));
            $data = $api->payment->fetch($payment->provider_reference);
            return [(string) $data->status, (int) $data->amount, strtoupper((string) $data->currency), (int) ($data->amount_refunded ?? 0)];
        }
        if ($payment->gateway === 'paystack') {
            $response = Http::withToken((string) GetpaymentMethod('paystack_secretkey'))
                ->get('https://api.paystack.co/transaction/verify/'.rawurlencode($payment->provider_reference));
            if (!$response->successful() || !($response->json('status') ?? false)) throw new \RuntimeException('Paystack verification failed.');
            $data = $response->json('data', []);
            return [(string) ($data['status'] ?? 'unknown'), (int) ($data['amount'] ?? 0), strtoupper((string) ($data['currency'] ?? '')), null];
        }
        throw new \RuntimeException('This payment provider cannot be reconciled through the configured API.');
    }

    private function providerPaymentSucceeded(string $gateway, string $status): bool
    {
        $status = strtolower($status);
        return match ($gateway) {
            'stripe' => $status === 'succeeded',
            'razorpay' => $status === 'captured',
            'paystack' => $status === 'success',
            default => false,
        };
    }

    private function normalizeRefundStatus(string $gateway, string $providerStatus): string
    {
        $status = strtolower($providerStatus);
        if (in_array($status, ['succeeded', 'processed', 'success'], true)) return 'succeeded';
        if (in_array($status, ['failed', 'cancelled', 'canceled', 'rejected'], true)) return 'failed';
        return 'processing';
    }

    private function currencyMultiplier(string $currency): int
    {
        return in_array(strtoupper($currency), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'], true) ? 1 : 100;
    }
}
