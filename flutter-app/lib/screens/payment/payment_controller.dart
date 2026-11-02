import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:swann_ott/components/app_dialog_widget.dart';
import 'package:swann_ott/controllers/base_controller.dart';
import 'package:swann_ott/generated/assets.dart';
import 'package:swann_ott/main.dart';
import 'package:swann_ott/screens/auth/model/app_configuration_res.dart';
import 'package:swann_ott/screens/content/model/content_model.dart';
import 'package:swann_ott/screens/coupon/model/coupon_list_model.dart';
import 'package:swann_ott/screens/payment/model/payment_model.dart';
import 'package:swann_ott/screens/payment/model/subscription_model.dart';
import 'package:swann_ott/services/in_app_purhcase_service.dart';
import 'package:swann_ott/screens/subscription/subscription_controller.dart';
import 'package:swann_ott/services/local_storage_service.dart';
import 'package:swann_ott/utils/colors.dart';
import 'package:swann_ott/utils/constants.dart';

import '../../network/core_api.dart';
import '../../utils/common_base.dart';
import '../coupon/coupon_list_controller.dart';
import '../subscription/model/subscription_plan_model.dart';
import 'payment_gateways/razor_pay_service.dart';
import 'payment_gateways/stripe_services.dart';

class PaymentController extends BaseController {
  RxBool showCoupon = false.obs;
  RxBool isPaymentLoading = false.obs;
  RxString paymentOption = PaymentMethods.PAYMENT_METHOD_STRIPE.obs;
  RxList<PaymentSetting> originalPaymentList = RxList();
  Rx<SubscriptionPlanModel> selectPlan = SubscriptionPlanModel().obs;
  RxDouble price = 0.0.obs;
  RxDouble discount = 0.0.obs;
  RxDouble rentPrice = 0.0.obs;
  RxBool isRent = false.obs;
  Rx<PaymentSetting> selectPayment = PaymentSetting().obs;
  ContentData contentData = ContentData();
  ContentModel contentModel =
      ContentModel(details: ContentData(), downloadData: DownloadDataModel());
  RentalData rentalData = RentalData();

  // Payment Class
  RazorPayService razorPayService = RazorPayService();
  Rx<Future<RxBool>> paymentListFuture = Future(() => false.obs).obs;

  // Coupon
  CouponListController couponController = CouponListController();

  PaymentMethod availablePaymentMethods = PaymentMethod(
    razorPay: RazorPay(),
    stripePay: StripePay(),
    payStackPay: PayStackPay(),
    paypalPay: PaypalPay(),
    flutterWavePay: FlutterwavePay(),
    inAppPurchase: InAppPurchase(),
  );

  @override
  void onInit() {
    final args = Get.arguments;
    if (args is List && args.isNotEmpty) {
      if (args[0] is ContentModel) {
        contentModel = (args[0] as ContentModel);
        contentData = (args[0] as ContentModel).details;
        contentData.id = (args[0] as ContentModel).id;
        update([contentData]);
      }
      if (args[1] is SubscriptionPlanModel) {
        selectPlan.value = args[1] as SubscriptionPlanModel;
        price.value = args[2];
        discount.value = args[3];
      } else if (args[1] is RentalData) {
        rentalData = (args[1] as RentalData);
        update([rentalData]);
        rentPrice.value = rentalData.discountedPrice.validate().toDouble();
        discount.value = rentalData.discount.toDouble();
        isRent.value = true;
      }
    }
    allApisCalls();
    super.onInit();
  }

  Future<void> allApisCalls() async {
    /// Fetch Coupon List
    fetchCouponList();
    await getAppConfigurations();
  }

  Future<void> fetchCouponList() async {
    if (isRent.value) return;
    setLoading(true);
    await couponController
        .getListData(selectedPlanId: selectPlan.value.planId.toString())
        .then((value) {
      couponController.listContent.removeWhere((coupon) =>
          coupon.discountType == Tax.fixed &&
          coupon.discount.validate().toDouble() >=
              selectPlan.value.totalPrice.toDouble());
      setLoading(false);
    }).onError((error, stackTrace) {
      setLoading(false);
      log('Coupon List Error: ${error.toString()}');
    });
  }

  Future<void> getAppConfigurations() async {
    isPaymentLoading(true);
    setLoading(true);
    await CoreServiceApis.getAvailablePaymentMethods().then((value) async {
      availablePaymentMethods = value.data;
      showCoupon.value = true;
      update([availablePaymentMethods]);

      final inAppPurchase = availablePaymentMethods.inAppPurchase;
      final storeApiKey =
          isIOS ? inAppPurchase.appleApiKey : inAppPurchase.googleApiKey;
      if (!isRent.value &&
          inAppPurchase.entitlementId.isNotEmpty &&
          storeApiKey.isNotEmpty) {
        await inAppPurchaseService.init(apiKey: storeApiKey);
        if (isLoggedIn.value)
          await inAppPurchaseService.retryPendingSubscriptionData();
      }

      paymentListFuture(Future(() async => getPayment()))
          .whenComplete(() => setLoading(false));
    }).onError((error, stackTrace) {
      toast(error.toString());
    }).whenComplete(() {
      setLoading(false);
      isPaymentLoading(false);
    });
  }

  ///Get Payment List
  Future<RxBool> getPayment({bool showLoader = true}) async {
    isLoading(showLoader);
    originalPaymentList.clear();

    // Mobile checkout is limited to gateways whose payment is created and verified by the backend.
    if (availablePaymentMethods.stripePay.stripePublicKey.isNotEmpty) {
      originalPaymentList.add(PaymentSetting(
        id: 0,
        title: locale.value.stripePay,
        type: PaymentMethods.PAYMENT_METHOD_STRIPE,
        liveValue: LiveValue(
            stripePublickey: availablePaymentMethods.stripePay.stripePublicKey),
      ));
    }
    if (availablePaymentMethods.razorPay.razorpayPublicKey.isNotEmpty) {
      originalPaymentList.add(PaymentSetting(
        id: 1,
        title: locale.value.razorPay,
        type: PaymentMethods.PAYMENT_METHOD_RAZORPAY,
        liveValue: LiveValue(
            razorKey: availablePaymentMethods.razorPay.razorpayPublicKey),
      ));
    }
    final store = availablePaymentMethods.inAppPurchase;
    if (!isRent.value &&
        store.entitlementId.isNotEmpty &&
        (isIOS ? store.appleApiKey : store.googleApiKey).isNotEmpty) {
      originalPaymentList.add(PaymentSetting(
        id: 2,
        title: locale.value.inAppPurchase,
        type: PaymentMethods.PAYMENT_METHOD_IN_APP_PURCHASE,
      ));
    }

    setLoading(false);
    return true.obs;
  }

  void handlePaymentSelection(PaymentSetting data) {
    selectPayment.value = data;
    paymentOption.value = data.type.validate();
    showCoupon(
        paymentOption.value != PaymentMethods.PAYMENT_METHOD_IN_APP_PURCHASE &&
            !isRent.value);
    update([selectPayment, paymentOption]);
  }

  /// handle Payment Click

  void handlePayNowClick(BuildContext context, VoidCallback onSuccess) {
    Get.bottomSheet(
      AppDialogWidget(
        title: isRent.value
            ? locale.value.doYouConfirmThis(contentData.name)
            : locale.value
                .doYouConfirmThisPlanWithPlanName(selectPlan.value.name),
        onAccept: () {
          if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_STRIPE) {
            payWithStripe(context, onSuccess);
          } else if (paymentOption.value ==
              PaymentMethods.PAYMENT_METHOD_RAZORPAY) {
            payWithRazorPay(context, onSuccess);
          } else if (paymentOption.value ==
                  PaymentMethods.PAYMENT_METHOD_IN_APP_PURCHASE &&
              !isRent.value) {
            payWithInAppPurchase(onSuccess);
          } else {
            toast(
                'This payment method is not available in the secure checkout flow yet.');
          }
        },
        image: isRent.value ? Assets.imagesRental : Assets.iconsCrown,
        imageColor: appColorPrimary,
        positiveText: locale.value.confirm,
        negativeText: locale.value.cancel,
      ),
    );
  }

  Future<void> payWithStripe(
      BuildContext context, VoidCallback onSuccess) async {
    setLoading(true);
    try {
      if ((!isRent.value && price.value <= 0) ||
          (isRent.value && rentPrice.value <= 0)) {
        final freeResponse = await CoreServiceApis.processMobileGatewayPayment(
          endpoint: isRent.value
              ? 'pay-per-view/process-payment'
              : 'subscription/process-payment',
          request: isRent.value
              ? {
                  'movie_id': contentModel.id,
                  'type': contentData.type,
                  'payment_method': 'free'
                }
              : {
                  'plan_id': selectPlan.value.planId,
                  'payment_method': 'free',
                  if (couponController.appliedCouponData.value.id > 0)
                    'coupon_id': couponController.appliedCouponData.value.id,
                },
        );
        _completePurchase(freeResponse, onSuccess);
        return;
      }

      final intent = await CoreServiceApis.createMobilePaymentIntent(
        request: isRent.value
            ? {
                'purchase_type': 'pay_per_view',
                'movie_id': contentModel.id,
                'type': contentData.type,
              }
            : {
                'purchase_type': 'subscription',
                'plan_id': selectPlan.value.planId,
                if (couponController.appliedCouponData.value.id > 0)
                  'coupon_id': couponController.appliedCouponData.value.id,
              },
      );

      await StripeServices.stripePaymentMethod(
        publicKey: intent['publishable_key'] as String,
        clientSecret: intent['client_secret'] as String,
        paymentIntentId: intent['payment_intent_id'] as String,
        loaderOnOff: (bool value) => isLoading(value),
        onComplete: (result) async {
          try {
            final verified = await CoreServiceApis.verifyMobilePaymentIntent(
              paymentIntentId: result['payment_intent_id'] as String,
            );
            _completePurchase(verified, onSuccess);
          } catch (error) {
            setLoading(false);
            errorSnackBar(error: error);
          }
        },
      );
    } catch (error) {
      setLoading(false);
      errorSnackBar(error: error);
    }
  }

  Future<void> payWithRazorPay(
      BuildContext context, VoidCallback onSuccess) async {
    setLoading(true);
    try {
      final endpoint = isRent.value
          ? 'pay-per-view/process-payment'
          : 'subscription/process-payment';
      final order = await CoreServiceApis.processMobileGatewayPayment(
        endpoint: endpoint,
        request: isRent.value
            ? {
                'movie_id': contentModel.id,
                'type': contentData.type,
                'payment_method': 'razorpay',
              }
            : {
                'plan_id': selectPlan.value.planId,
                'payment_method': 'razorpay',
                if (couponController.appliedCouponData.value.id > 0)
                  'coupon_id': couponController.appliedCouponData.value.id,
              },
      );

      if (order['is_free'] == true ||
          order['success'] == true && isRent.value) {
        _completePurchase(order, onSuccess);
        return;
      }

      await razorPayService.init(
        publicKey: order['key'] as String,
        orderId: order['order_id'] as String,
        amountMinor: (order['amount'] as num).round(),
        currency: order['currency'] as String,
        onComplete: (payment) async {
          setLoading(true);
          try {
            final verified = await CoreServiceApis.verifyMobileGatewayPayment(
              endpoint: isRent.value
                  ? 'pay-per-view/verify-payment'
                  : 'subscription/verify-payment',
              request: {
                'gateway': 'razorpay',
                'payment_id': payment['payment_id'],
                'order_id': payment['order_id'],
                'signature': payment['signature'],
              },
            );
            _completePurchase(verified, onSuccess);
          } catch (error) {
            setLoading(false);
            errorSnackBar(error: error);
          }
        },
        onFailure: () => setLoading(false),
      );
      setLoading(false);
    } catch (error) {
      setLoading(false);
      errorSnackBar(error: error);
    }
  }

  Future<void> payWithInAppPurchase(VoidCallback onSuccess) async {
    if (isRent.value) return;
    final package =
        inAppPurchaseService.getSelectedPlanFromRevenueCat(selectPlan.value);
    if (package == null) {
      toast('This plan is not available in the app store.');
      return;
    }

    setLoading(true);
    await inAppPurchaseService.startPurchase(
      selectedRevenueCatPackage: package,
      onComplete: (_) async {
        try {
          await setBoolToLocal(
              SharedPreferenceConst.IS_SUBSCRIPTION_PURCHASE_RESTORE_REQUIRED,
              true);
          await setJsonToLocal(SharedPreferenceConst.PURCHASE_REQUEST,
              selectPlan.value.toJson());
          final result = await CoreServiceApis.syncInAppPurchaseSubscription();
          if (result['has_active_subscription'] != true ||
              result['subscription'] is! Map) {
            throw StateError(
                'The store purchase is not active for this account yet.');
          }
          await inAppPurchaseService.clearPendingSubscriptionData();
          _completePurchase(result, onSuccess);
        } catch (error) {
          setLoading(false);
          errorSnackBar(error: error);
        }
      },
      onFailure: () => setLoading(false),
    );
  }

  Future<void> restoreInAppPurchases() async {
    if (!isLoggedIn.value) {
      toast('Sign in to restore purchases.');
      return;
    }
    setLoading(true);
    try {
      final customerInfo = await inAppPurchaseService.restorePurchases();
      final result = await CoreServiceApis.syncInAppPurchaseSubscription();
      if (result['has_active_subscription'] == true &&
          result['subscription'] is Map) {
        updateSubscriptionDetails(SubscriptionResponseModel.fromJson({
          'status': true,
          'data': Map<String, dynamic>.from(result['subscription'] as Map),
        }));
        setLoading(false);
        toast('Purchases restored.');
      } else {
        setLoading(false);
        toast(customerInfo == null
            ? 'Unable to restore purchases. Please try again.'
            : 'No active subscription was found.');
      }
    } catch (error) {
      setLoading(false);
      errorSnackBar(error: error);
    }
  }

  void _completePurchase(Map<String, dynamic> result, VoidCallback onSuccess) {
    if (isRent.value) {
      Get.back();
      onSuccess.call();
      setLoading(false);
      return;
    }

    final subscription = result['subscription'];
    if (subscription is Map) {
      updateSubscriptionDetails(SubscriptionResponseModel.fromJson({
        'status': true,
        'data': Map<String, dynamic>.from(subscription),
      }));
    }
    Get.back();
    Get.back();
    onSuccess.call();
    setLoading(false);
  }

  void updateSubscriptionDetails(SubscriptionResponseModel value) {
    currentSubscription(value.data);
    if (currentSubscription.value.level > -1 &&
        currentSubscription.value.planType.isNotEmpty &&
        currentSubscription.value.planType
            .any((element) => element.slug == SubscriptionTitle.videoCast)) {
      isCastingSupported(currentSubscription.value.planType
          .firstWhere((element) => element.slug == SubscriptionTitle.videoCast)
          .limitationValue
          .getBoolInt());
    } else {
      isCastingSupported(false);
    }
    currentSubscription.value.activePlanInAppPurchaseIdentifier = isIOS
        ? currentSubscription.value.appleInAppPurchaseIdentifier
        : currentSubscription.value.googleInAppPurchaseIdentifier;
    setJsonToLocal(SharedPreferenceConst.CACHE_USER_SUBSCRIPTION_DATA,
        value.data.toJson());
    setJsonToLocal(SharedPreferenceConst.USER_DATA, loginUserData.toJson());
  }

  void removeAppliedCoupon({bool isDataFetch = true}) {
    if (isRent.value) return;
    couponController.appliedCouponData.value.isCouponApplied = false;
    couponController.appliedCouponData.value = CouponDataModel();
    SubscriptionController subscriptionController =
        Get.find<SubscriptionController>();
    subscriptionController.calculateTotalPrice();

    /// Fetch Coupon List
    if (isDataFetch) fetchCouponList();
  }

  @override
  void onClose() {
    removeAppliedCoupon();
    super.onClose();
  }
}
