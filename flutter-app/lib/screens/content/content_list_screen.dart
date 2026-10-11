import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:swann_ott/components/app_no_data_widget.dart';
import 'package:swann_ott/components/app_scaffold.dart';
import 'package:swann_ott/main.dart';
import 'package:swann_ott/routes/app_routes.dart';
import 'package:swann_ott/screens/content/components/content_poster_component.dart';
import 'package:swann_ott/screens/content/content_list_controller.dart';
import 'package:swann_ott/screens/content/content_list_shimmer.dart';
import 'package:swann_ott/screens/content/model/content_model.dart';
import 'package:swann_ott/screens/slider/banner_widget.dart';
import 'package:swann_ott/utils/common_base.dart';
import 'package:swann_ott/utils/empty_error_state_widget.dart';
import 'package:swann_ott/utils/extension/string_extension.dart';

class ContentListScreen extends StatelessWidget {
  final String? title;

  const ContentListScreen({super.key, this.title});

  @override
  Widget build(BuildContext context) {
    // Get dynamic grid size (width + spacing)
    final dynamicSpacing = getDynamicSpacing();

    return GetBuilder(
      init: Get.find<ContentListController>(),
      builder: (contentListController) => Obx(
        () {
          final sliderCtrl = contentListController.sliderController;
          final bool showBanner =
              sliderCtrl.isLoading.value || sliderCtrl.listContent.isNotEmpty;

          return NewAppScaffold(
            isPinnedAppbar: true,
            scrollController: contentListController.scrollController,
            expandedHeight: showBanner ? Get.height * 0.40 : kToolbarHeight,
            appBarTitleText:
                title ?? sliderCtrl.sliderType.value.getContentTypeTitle(),
            isBodyFullScreen: true,
            topbarChild: showBanner
                ? BannerWidget(
                    expandedHeight: Get.height * 0.50,
                    sliderController: sliderCtrl,
                    tag: AppRoutes.banner,
                  )
                : const SizedBox.shrink(),
            onRefresh: contentListController.init,
            bodySlivers: [
              SliverPadding(
                padding: EdgeInsets.only(left: 12, right: 12, top: 12),
                sliver: Obx(
                  () {
                    if (contentListController.listContent.isEmpty &&
                        contentListController.isLoading.value) {
                      return SliverToBoxAdapter(
                        child: ContentListShimmer(
                          width: dynamicSpacing.$1,
                          spacing: dynamicSpacing.$2,
                        ),
                      );
                    }
                    if (contentListController.listContent.isEmpty &&
                        !contentListController.isLoading.value) {
                      return SliverToBoxAdapter(
                        child: AppNoDataWidget(
                          title: locale.value.noContentFound,
                          subTitle:
                              "${locale.value.no} ${contentListController.argumentData.type.getContentTypeTitle()} ${locale.value.isAvailableInThisCategory}",
                          retryText: locale.value.reload,
                          imageWidget: const ErrorStateWidget(),
                          onRetry: contentListController.onRetry,
                        ),
                      );
                    }
                    return SliverGrid(
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 3,
                        crossAxisSpacing: dynamicSpacing.$2,
                        mainAxisSpacing: dynamicSpacing.$2,
                        childAspectRatio: 0.70,
                      ),
                      delegate: SliverChildBuilderDelegate(
                        (context, index) {
                          final PosterDataModel content =
                              contentListController.listContent[index];
                          return ContentListComponent(contentData: content);
                        },
                        childCount: contentListController.listContent.length,
                      ),
                    );
                  },
                ),
              ),
              if (contentListController.isLoading.value &&
                  contentListController.listContent.isNotEmpty)
                SliverPadding(
                  padding: EdgeInsets.only(left: 12, right: 12, top: 12),
                  sliver: SliverToBoxAdapter(
                    child: ContentListShimmer(
                      width: dynamicSpacing.$1,
                      spacing: dynamicSpacing.$2,
                      length: 9,
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
