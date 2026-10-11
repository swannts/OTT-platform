import 'package:swann_ott/controllers/base_controller.dart';
import 'package:swann_ott/main.dart';
import 'package:swann_ott/network/core_api.dart';
import 'package:swann_ott/screens/auth/model/about_page_res.dart';
import 'package:swann_ott/services/local_storage_service.dart';
import 'package:swann_ott/utils/common_functions.dart';
import 'package:swann_ott/utils/constants.dart';

class HelpAndSupportController extends BaseListController<AboutDataModel> {
  @override
  void onInit() {
    super.onInit();
  }

  @override
  void onReady() {
    getListData(showLoader: false);
    super.onReady();
  }

  @override
  Future<void> getListData({bool showLoader = true}) async {
    setLoading(showLoader);
    await listContentFuture(CoreServiceApis.getPageList()).then((value) {
      isLastPage(true);
      listContent(value);
      if (!listContent.any((element) => element.slug == AppPages.faq)) {
        listContent.add(
          AboutDataModel(
            slug: AppPages.faq,
            name: locale.value.faqs,
          ),
        );
      }
      appPageList(value); // data in the observable list
      setIntToLocal(SharedPreferenceConst.PAGE_LAST_CALL_TIME, DateTime.timestamp().millisecondsSinceEpoch);
    }).catchError((e) {
      throw e;
    }).whenComplete(() => setLoading(false));
  }
}