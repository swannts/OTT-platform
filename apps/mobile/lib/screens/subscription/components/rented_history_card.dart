import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:swann_ott/generated/assets.dart';
import 'package:swann_ott/main.dart';
import 'package:swann_ott/screens/subscription/model/rental_history_model.dart';
import 'package:swann_ott/utils/colors.dart';
import 'package:swann_ott/utils/common_base.dart';
import 'package:swann_ott/utils/price_widget.dart';

class RentedHistoryCard extends StatelessWidget {
  final RentalHistoryItem rentalHistory;
  final VoidCallback onDownloadClick;

  const RentedHistoryCard({super.key, required this.rentalHistory, required this.onDownloadClick});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16),
      decoration: boxDecorationDefault(
        borderRadius: radius(8),
        color: cardColor,
        border: Border.all(color: borderColorDark),
      ),
      child: Column(
        children: [
          Container(
            decoration: boxDecorationDefault(color: Colors.transparent, borderRadius: radius(22)),
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              children: [
                Text(rentalHistory.name, style: boldTextStyle()).expand(),
                PriceWidget(
                  price: rentalHistory.total,
                  size: 22,
                  color: context.primaryColor,
                ),
              ],
            ),
          ),
          16.height,
          if (rentalHistory.date.isNotEmpty && rentalHistory.expireDate.isNotEmpty)
            TextIcon(
              prefix: IconWidget(
                imgPath: Assets.iconsClock,
                color: iconColor,
                size: 16,
              ),
              text: "${locale.value.validity}: ${rentalHistory.date} to ${rentalHistory.expireDate}",
              textStyle: commonSecondaryTextStyle(size: 12),
            ),
          8.height,
          Divider(
            color: borderColorDark,
            height: 8,
          ),
          TextButton(
            onPressed: onDownloadClick,
            style: ButtonStyle(
              padding: WidgetStatePropertyAll(EdgeInsets.zero),
              visualDensity: VisualDensity.compact,
            ),
            child: TextIcon(
              spacing: 12,
              prefix: IconWidget(
                imgPath: Assets.iconsDownload,
                color: appColorPrimary,
                size: 16,
              ),
              text: locale.value.downloadInvoice,
              textStyle: boldTextStyle(color: appColorPrimary),
            ),
          )
        ],
      ),
    );
  }
}