import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:swann_ott/components/app_no_data_widget.dart';
import 'package:swann_ott/components/app_scaffold.dart';
import 'package:swann_ott/main.dart';
import 'package:swann_ott/screens/content/components/content_poster_component.dart';
import 'package:swann_ott/screens/content/content_list_shimmer.dart';
import 'package:swann_ott/screens/content/model/content_model.dart';
import 'package:swann_ott/screens/person/components/person_details.dart';
import 'package:swann_ott/screens/person/components/person_profile.dart';
import 'package:swann_ott/screens/person/person_controller.dart';
import 'package:swann_ott/utils/colors.dart';
import 'package:swann_ott/utils/common_base.dart';
import 'package:swann_ott/utils/common_functions.dart';
import 'package:swann_ott/utils/empty_error_state_widget.dart';
import 'package:swann_ott/utils/extension/string_extension.dart';

class PersonDetailScreen extends StatelessWidget {
  final Cast personDet;

  PersonDetailScreen({super.key, required this.personDet});

  @override
  Widget build(BuildContext context) {
    final dynamicSpacing = getDynamicSpacing();
    return GetBuilder(
      init: Get.find<PersonController>(),
      builder: (personCont) => NewAppScaffold(
        isPinnedAppbar: true,
        scrollController: personCont.scrollController,
        onRefresh: personCont.init,
        scaffoldBackgroundColor: appScreenBackgroundDark,
        expandedHeight: Get.height * 0.50,
        topbarChild: Stack(
          alignment: AlignmentDirectional.bottomEnd,
          children: [
            PersonProfileWidget(personDetail: personDet),
            Obx(
              () => PersonDetailsWidget(personDet: personCont.castDetails.value),
            )
          ],
        ),
        appBarBottomWidget: Obx(
          () => personCont.availableFilter.isNotEmpty
              ? Align(
                  alignment: Alignment.centerLeft,
                  child: HorizontalList(
                    itemCount: personCont.availableFilter.length,
                    itemBuilder: (context, index) {
                      String tab = personCont.availableFilter[index];
                      return Obx(
                        () => Container(
                          padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          decoration: boxDecorationDefault(
                            color: personCont.currentFilterIndex.value == index ? appColorPrimary : Colors.transparent,
                            borderRadius: radius(20),
                            border: Border.all(color: personCont.currentFilterIndex.value == index ? appColorPrimary : iconColor),
                          ),
                          child: Text(
                            tab.getContentTypeTitle(),
                            style: primaryTextStyle(size: 14),
                          ),
                        ).onTap(
                          () {
                            personCont.currentFilterIndex.value = index;
                            personCont.getListData();
                          },
                          splashColor: Colors.transparent,
                          highlightColor: Colors.transparent,
                        ),
                      );
                    },
                  ),
                )
              : const Offstage(),
        ),
        body: appConfigs.value.enableMovie || appConfigs.value.enableTvShow
            ? Column(
                spacing: 16,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    locale.value.moviesTvShowsOf(personDet.name),
                    style: boldTextStyle(),
                  ),
                  Obx(
                    () => SnapHelperWidget(
                      future: personCont.listContentFuture.value,
                      loadingWidget: ContentListShimmer(
                        width: dynamicSpacing.$1,
                        spacing: dynamicSpacing.$2,
                      ),
                      errorBuilder: (error) {
                        return AppNoDataWidget(
                          height: Get.height * 0.30,
                          title: error,
                          retryText: locale.value.reload,
                          imageWidget: const ErrorStateWidget(),
                          onRetry: personCont.onRetry,
                        ).visible(!personCont.isLoading.value);
                      },
                      onSuccess: (res) {
                        return Obx(() {
                          if (personCont.isLoading.value) {
                            return ContentListShimmer(
                              width: dynamicSpacing.$1,
                              spacing: dynamicSpacing.$2,
                            );
                          }
                          if (personCont.listContent.isEmpty && !personCont.isLoading.value) {
                            return AppNoDataWidget(
                              height: Get.height * 0.30,
                              title: locale.value.noContentFound,
                              subTitle: locale.value.noMoviesOrTvShowsFeaturing('${personDet.designation} ${personDet.name}'),
                              retryText: locale.value.reload,
                              onRetry: personCont.onRetry,
                              imageWidget: const EmptyStateWidget(),
                            );
                          }
                          return AnimatedWrap(
                            listAnimationType: commonListAnimationType,
                            spacing: getDynamicSpacing().$2,
                            runSpacing: getDynamicSpacing().$2,
                            itemCount: personCont.listContent.length,
                            itemBuilder: (context, index) {
                              final PosterDataModel content = personCont.listContent[index];
                              return ContentListComponent(contentData: content);
                            },
                          );
                        });
                      },
                    ),
                  ),
                ],
              )
            : Offstage(),
      ),
    );
  }
}