import 'package:intl/intl.dart';
import 'package:swann_ott/utils/common_functions.dart';
import 'package:swann_ott/utils/extension/string_extension.dart';

import '../constants.dart';

extension DateExtension on DateTime {
  String formatDateYYYYmmdd() {
    final formatter = DateFormat(DateFormatConst.yyyy_MM_dd);
    return formatter.format(this);
  }

  String appConfigurationDateFormate() {
    return appConfigs.value.dateFormate.formatWithPhp(this);
  }
}
