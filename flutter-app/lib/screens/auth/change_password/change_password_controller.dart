import 'package:flutter/material.dart';
import 'package:swann_ott/controllers/base_controller.dart';
import 'package:swann_ott/services/auth_token_storage.dart';
import 'package:swann_ott/utils/api_end_points.dart';
import 'package:swann_ott/utils/common_base.dart';

import '../../../network/auth_apis.dart';
import '../../../utils/common_functions.dart';

class ChangePasswordController extends BaseController {
  final GlobalKey<FormState> changePassFormKey = GlobalKey();
  TextEditingController oldPasswordCont = TextEditingController();
  TextEditingController newPasswordCont = TextEditingController();
  TextEditingController confirmPasswordCont = TextEditingController();

  FocusNode oldPasswordFocus = FocusNode();
  FocusNode newPasswordFocus = FocusNode();
  FocusNode confirmPasswordFocus = FocusNode();

  Future<void> saveForm(VoidCallback onSuccess) async {
    if (isLoading.value) return;
    setLoading(true);

    final Map<String, dynamic> req = {
      ApiRequestKeys.oldPassword: oldPasswordCont.text.trim(),
      ApiRequestKeys.newPassword: confirmPasswordCont.text.trim(),
    };

    await AuthServiceApis.changePasswordApi(request: req).then((value) async {
      setLoading(false);
      loginUserData.value.apiToken = value.data.apiToken;
      await AuthTokenStorage.write(value.data.apiToken);
      logOutFromAllDevice(
        loaderOnOff: (isLoading) {
          setLoading(isLoading);
        },
        showLoader: false,
        onSuccess: () {
          onSuccess.call();
        },
        showSuccess: false,
      ).catchError((e) {
        onSuccess.call();
      });
    }).catchError((e) {
      setLoading(false);
      errorSnackBar(error: e);
    });
  }

  void clearForm() {
    oldPasswordCont.clear();
    newPasswordCont.clear();
    confirmPasswordCont.clear();
  }

  @override
  void onClose() {
    clearForm();
    super.onClose();
  }
}
