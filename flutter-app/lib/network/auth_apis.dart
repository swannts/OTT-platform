import 'package:nb_utils/nb_utils.dart';
import 'package:swann_ott/screens/profile/model/profile_detail_resp.dart';
import 'package:swann_ott/services/local_storage_service.dart';
import 'package:swann_ott/services/auth_token_storage.dart';
import 'package:swann_ott/utils/common_base.dart';

import '../models/base_response_model.dart';
import '../screens/auth/model/change_password_res.dart';
import '../screens/auth/model/login_response.dart';
import '../screens/auth/model/notification_model.dart';
import '../utils/api_end_points.dart';
import '../utils/common_functions.dart';
import '../utils/constants.dart';
import 'network_utils.dart';

class AuthServiceApis {
  static Future<void> createUser({required Map request}) async {
    await getApiResponse(APIEndPoints.register, request: request, method: HttpMethodType.POST);
  }

  static Future<void> loginUser({required Map<String, dynamic> request, bool isSocialLogin = false}) async {
    if (await isIqonicProduct && (request[ApiRequestKeys.mobile] == Constants.defaultNumber || request[ApiRequestKeys.email] == Constants.DEFAULT_EMAIL)) {
      setBoolToLocal(SharedPreferenceConst.IS_DEMO_USER, true);
    }
    setBoolToLocal(SharedPreferenceConst.IS_SOCIAL_LOGIN_IN, isSocialLogin);

    UserResponse userData = UserResponse.fromJson(
      await getApiResponse(
        isSocialLogin ? APIEndPoints.socialLogin : APIEndPoints.login,
        request: request,
        method: HttpMethodType.POST,
      ),
    );
    await storeUserData(userData);
  }

  static Future<BaseResponseModel> checkMobileNumberExist({required Map<String, dynamic> request, bool isSocialLogin = false}) async {

    BaseResponseModel userData = BaseResponseModel.fromJson(
      await getApiResponse(
        APIEndPoints.checkMobileNoExist,
        request: request,
        method: HttpMethodType.POST,
      ),
    );
    return userData;
  }

  static Future<void> storeUserData(UserResponse userData) async {
    if (userData.userData.apiToken.isEmpty) {
      throw const FormatException('The authentication response did not include an access token.');
    }
    await AuthTokenStorage.write(userData.userData.apiToken);
    isLoggedIn(true);

    setBoolToLocal(SharedPreferenceConst.IS_LOGGED_IN, true);
    loginUserData(userData.userData);
    currentSubscription(userData.userData.planDetails);

    if (currentSubscription.value.level > -1 && currentSubscription.value.planType.isNotEmpty && currentSubscription.value.planType.any((element) => element.slug == SubscriptionTitle.videoCast)) {
      isCastingSupported(currentSubscription.value.planType.firstWhere((element) => element.slug == SubscriptionTitle.videoCast).limitationValue.getBoolInt());
    } else {
      isCastingSupported(false);
    }
    currentSubscription.value.activePlanInAppPurchaseIdentifier = isIOS ? currentSubscription.value.appleInAppPurchaseIdentifier : currentSubscription.value.googleInAppPurchaseIdentifier;
    setJsonToLocal(SharedPreferenceConst.USER_DATA, loginUserData.value.toJson());
    setJsonToLocal(SharedPreferenceConst.CACHE_USER_SUBSCRIPTION_DATA, userData.userData.planDetails.toJson());
  }

  static Future<void> logoutCurrentDevice() async {
    try {
      await getApiResponse(APIEndPoints.logout);
    } finally {
      await AuthTokenStorage.clear();
    }
  }

  static Future<ChangePasswordResponse> changePasswordApi({required Map request}) async {
    return ChangePasswordResponse.fromJson(await getApiResponse(APIEndPoints.changePassword, request: request, method: HttpMethodType.POST));
  }

  static Future<BaseResponseModel> forgotPasswordApi({required Map request}) async {
    return BaseResponseModel.fromJson(await getApiResponse(APIEndPoints.forgotPassword, request: request, method: HttpMethodType.POST));
  }

  static Future<List<NotificationData>> getNotificationDetail({
    int page = 1,
    required List<NotificationData> notifications,
    Function(bool)? lastPageCallBack,
    bool isMarkAllAsRead = false,
  }) async {
    String params = "type=mark_as_read";
    int perPage = determinePerPage();
    NotificationResponse notificationRes = NotificationResponse.fromJson(
      await getApiResponse(
        getEndPoint(
          endPoint: APIEndPoints.notificationList,
          page: page,
          perPages: perPage,
          params: isMarkAllAsRead ? [params] : [],
        ),
      ),
    );
    if (page == 1) notifications.clear();
    notifications.addAll(notificationRes.notificationData);
    lastPageCallBack?.call(notificationRes.notificationData.length != perPage);
    appUnReadNotificationCount(0);
    setIntToLocal(SharedPreferenceConst.CACHE_UNREAD_NOTIFICATION_COUNT, appUnReadNotificationCount.value);
    return notifications;
  }

  static Future<BaseResponseModel> deleteNotification({
    String type = '',
    required String notificationId,
  }) async {
    List<String> params = [];
    if (type.isNotEmpty) {
      params.add('${ApiRequestKeys.typeKey}=$type');
    }
    if (notificationId.isNotEmpty) {
      params.add('${ApiRequestKeys.idKey}=$notificationId');
    }

    BaseResponseModel response = BaseResponseModel.fromJson(
      await getApiResponse(
        getEndPoint(
          endPoint: APIEndPoints.deleteNotification,
          params: params,
        ),
        method: HttpMethodType.POST,
      ),
    );
    return response;
  }

  static Future<BaseResponseModel> deviceLogoutApi({required String deviceId}) async {
    final id = int.tryParse(deviceId);
    if (id == null || id < 1) {
      throw const FormatException('The selected device identifier is invalid.');
    }
    return BaseResponseModel.fromJson(await getApiResponse(
      getEndPoint(endPoint: APIEndPoints.deviceLogout, params: ['id=$id']),
    ));
  }

  static Future<BaseResponseModel> revokeDeviceSession({required String deviceId, required String managementToken}) async {
    final id = int.tryParse(deviceId);
    if (id == null || id < 1 || managementToken.isEmpty) {
      throw const FormatException('The device revocation authorization is invalid.');
    }
    final response = await getApiResponse(
      APIEndPoints.deviceSessionRevoke,
      request: {'device_id': id},
      method: HttpMethodType.POST,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json; charset=utf-8',
        'Authorization': 'Bearer $managementToken',
      },
    );
    return BaseResponseModel.fromJson(Map<String, dynamic>.from(response));
  }

  static Future<BaseResponseModel> deleteAccountCompletely() async {
    return BaseResponseModel.fromJson(await getApiResponse(APIEndPoints.deleteUserAccount));
  }

  static Future<BaseResponseModel> revokeAllDeviceSessions({String? managementToken}) async {
    if (managementToken == null || managementToken.isEmpty) {
      return BaseResponseModel.fromJson(await getApiResponse(APIEndPoints.logOutAll));
    }
    final response = await getApiResponse(
      APIEndPoints.deviceSessionsRevokeAll,
      request: const {},
      method: HttpMethodType.POST,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json; charset=utf-8',
        'Authorization': 'Bearer $managementToken',
      },
    );
    return BaseResponseModel.fromJson(Map<String, dynamic>.from(response));
  }

  //Edit Profile API

  static Future<ProfileDetailResponse> updateProfile({required Map<String, dynamic> request, String imageFile = ''}) async {
    ProfileDetailResponse baseResponse = ProfileDetailResponse.fromJson(
      await getMultiPartResponse(
        endPoint: getEndPoint(endPoint: APIEndPoints.editProfile),
        filePaths: [imageFile],
        request: request,
        fileKey: ApiRequestKeys.fileUrl,
      ),
    );

    return baseResponse;
  }
}
