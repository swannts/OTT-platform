import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:swann_ott/components/cached_image_widget.dart';
import 'package:swann_ott/screens/payment/model/payment_model.dart';
import 'package:swann_ott/utils/colors.dart';
import 'package:swann_ott/utils/common_base.dart';
import 'package:swann_ott/utils/extension/string_extension.dart';

import '../payment_controller.dart';

class PaymentCardComponent extends StatelessWidget {
  final PaymentSetting paymentDetails;

  PaymentCardComponent({super.key, required this.paymentDetails});

  final PaymentController paymentCont = Get.find<PaymentController>();

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => GestureDetector(
        onTap: () {
          paymentCont.handlePaymentSelection(paymentDetails);
        },
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: boxDecorationDefault(
            borderRadius: BorderRadius.circular(6),
            color: cardColor,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.start,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              CachedImageWidget(
                url: paymentDetails.type.getPaymentLogo(),
                width: 16,
                height: 16,
              ),
              12.width,
              Text(
                paymentDetails.title.validate(),
                style: commonW500PrimaryTextStyle(),
              ).expand(),
              if (paymentDetails.id == paymentCont.selectPayment.value.id)
                const Icon(
                  Icons.radio_button_checked_rounded,
                  size: 18,
                  color: appColorPrimary,
                )
              else
                const Icon(
                  Icons.radio_button_off_rounded,
                  size: 18,
                  color: darkGrayColor,
                ),
            ],
          ),
        ),
      ),
    );
  }
}