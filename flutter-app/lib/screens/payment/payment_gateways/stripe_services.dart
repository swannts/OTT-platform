import 'package:flutter/material.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:swann_ott/main.dart';

import '../../../configs.dart';
import '../../../utils/colors.dart';

class StripeServices {
  static Future<void> stripePaymentMethod({
    required Function(bool) loaderOnOff,
    required Function(Map<String, dynamic>) onComplete,
    required String publicKey,
    required String clientSecret,
    required String paymentIntentId,
  }) async {
    loaderOnOff(true);
    try {
      Stripe.publishableKey = publicKey;
      Stripe.merchantIdentifier = STRIPE_merchantIdentifier;
      await Stripe.instance.applySettings();

      await Stripe.instance.initPaymentSheet(
        paymentSheetParameters: SetupPaymentSheetParameters(
          paymentIntentClientSecret: clientSecret,
          style: isDarkMode.value ? ThemeMode.dark : ThemeMode.light,
          appearance: const PaymentSheetAppearance(
            colors: PaymentSheetAppearanceColors(primary: appColorPrimary),
          ),
          merchantDisplayName: APP_NAME,
          billingDetails: BillingDetails(
            name: loginUserData.value.firstName,
            email: loginUserData.value.email,
            phone: loginUserData.value.mobile,
          ),
        ),
      );
      await Stripe.instance.presentPaymentSheet();
      onComplete({'payment_intent_id': paymentIntentId});
    } catch (error) {
      toast(locale.value.paymentFailedMessage);
      log('Stripe PaymentSheet failed: $error');
      loaderOnOff(false);
    }
  }
}
