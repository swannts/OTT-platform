import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:swann_ott/components/cached_image_widget.dart';
import 'package:swann_ott/generated/assets.dart';
import 'package:swann_ott/screens/profile/edit_profile/edit_profile_controller.dart';
import 'package:swann_ott/utils/colors.dart';
import 'package:swann_ott/utils/common_base.dart';
import 'package:swann_ott/utils/common_functions.dart';
import 'package:swann_ott/utils/constants.dart';

class ProfilePicComponent extends StatelessWidget {
  ProfilePicComponent({super.key});

  final EditProfileController profileCont = Get.find<EditProfileController>();

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => Stack(
        children: [
          CachedImageWidget(
            url: profileCont.imageFile.value.isNotEmpty
                ? profileCont.imageFile.value
                : profileCont.profilePic.value.isNotEmpty
                    ? profileCont.profilePic.value
                    : Assets.iconsUserCircle,
            height: 120,
            width: 120,
            circle: true,
            fit: BoxFit.cover,
          ).paddingBottom(8),
          PositionedDirectional(
            bottom: 10,
            end: 2,
            child: InkWell(
              onTap: () {
                profileCont.chooseImageSource(context);
              },
              child: Container(
                height: 30,
                width: 30,
                padding: const EdgeInsets.all(4),
                decoration: boxDecorationDefault(
                  color: appColorPrimary,
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: IconWidget(
                  imgPath: Assets.iconsCamera,
                  size: 16,
                ),
              ),
            ),
          ).visible(loginUserData.value.loginType != LoginTypeConst.loginTypeGoogle && loginUserData.value.loginType != LoginTypeConst.loginTypeApple),
        ],
      ),
    );
  }
}