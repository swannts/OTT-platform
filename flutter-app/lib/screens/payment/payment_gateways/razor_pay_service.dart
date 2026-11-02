import 'package:nb_utils/nb_utils.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:swann_ott/configs.dart';
import 'package:swann_ott/main.dart';

class RazorPayService {
  final Razorpay _razorpay = Razorpay();
  late Function(Map<String, dynamic>) _onComplete;
  late Function() _onFailure;

  Future<void> init({
    required String publicKey,
    required String orderId,
    required int amountMinor,
    required String currency,
    required Function(Map<String, dynamic>) onComplete,
    required Function() onFailure,
  }) async {
    _onComplete = onComplete;
    _onFailure = onFailure;
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);

    _razorpay.open({
      'key': publicKey,
      'amount': amountMinor,
      'name': APP_NAME,
      'order_id': orderId,
      'description': APP_NAME,
      'image': APP_MINI_LOGO_URL,
      'currency': currency,
      'prefill': {
        'contact': loginUserData.value.mobile,
        'email': loginUserData.value.email,
      },
      'external': {
        'wallets': ['paytm'],
      },
    });
  }

  void _handlePaymentSuccess(PaymentSuccessResponse response) {
    final paymentId = response.paymentId;
    final orderId = response.orderId;
    final signature = response.signature;
    if (paymentId == null || orderId == null || signature == null) {
      toast(locale.value.paymentFailedMessage);
      _onFailure();
      return;
    }
    _onComplete({
      'payment_id': paymentId,
      'order_id': orderId,
      'signature': signature,
    });
  }

  void _handlePaymentError(PaymentFailureResponse response) {
    toast(locale.value.paymentFailedMessage);
    _onFailure();
  }

  void _handleExternalWallet(ExternalWalletResponse response) {
    if (response.walletName != null) toast(response.walletName!);
  }
}
