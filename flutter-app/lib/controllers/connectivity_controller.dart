import 'dart:async';
import 'dart:io';
import 'dart:ui';

import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:nb_utils/nb_utils.dart';
import 'package:streamit_laravel/configs.dart';

class ConnectivityController extends GetxController {
  static ConnectivityController get instance => Get.find();

  RxBool hasConnection = true.obs;

  @override
  void onInit() {
    super.onInit();

    Connectivity().onConnectivityChanged.listen((result) async {
      bool newStatus = result != ConnectivityResult.none;

      if (newStatus) {
        newStatus = await checkInternetConnection();
      }

      // Auto trigger actions if connection is restored
      if (!hasConnection.value && newStatus) {
        onInternetRestored?.call();
      }

      hasConnection.value = newStatus;
    });
  }

  /// Callback you can set from SplashScreen
  VoidCallback? onInternetRestored;

  Future<bool> checkInternetConnection() async {
    try {
      if (kIsWeb) {
        final response = await http
            .get(Uri.parse('${BASE_URL}v3/app-configuration'))
            .timeout(const Duration(seconds: 8));
        return response.statusCode == 200;
      }

      final result = await InternetAddress.lookup('google.com');
      return result.isNotEmpty;
    } catch (_) {
      return false;
    }
  }
}
