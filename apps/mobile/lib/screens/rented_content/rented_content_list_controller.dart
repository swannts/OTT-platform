import 'package:swann_ott/controllers/base_controller.dart';
import 'package:swann_ott/main.dart';
import 'package:swann_ott/network/core_api.dart';

import 'package:get/get.dart';
import 'package:swann_ott/screens/content/model/content_model.dart';
import 'package:swann_ott/utils/constants.dart';

class RentedContentListController extends BaseListController<PosterDataModel> {
  @override
  void onInit() {
    getListData(showLoader: false);
    super.onInit();
  }

  List<String> tabs = [locale.value.all, locale.value.movie, locale.value.video, locale.value.episodes];

  RxString selectedTab = locale.value.all.obs;

  List<PosterDataModel> get filteredList {
    if (selectedTab.value == locale.value.all) return listContent;
    if (selectedTab.value == locale.value.movie) return listContent.where((element) => element.details.type == VideoType.movie).toList();
    if (selectedTab.value == locale.value.video) return listContent.where((element) => element.details.type == VideoType.video).toList();
    if (selectedTab.value == locale.value.episodes) return listContent.where((element) => element.details.type == VideoType.episode).toList();
    return listContent;
  }

  @override
  Future<void> getListData({bool showLoader = true}) async {
    setLoading(showLoader);
    await listContentFuture(
      CoreServiceApis.getRentedContent(
        page: currentPage.value,
        rentedContentList: listContent,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).catchError((e) {
      throw e;
    }).whenComplete(() => isLoading(false));
  }
}