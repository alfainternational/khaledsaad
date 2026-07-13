import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';

/// المحتوى العام لتجربة الضيف (بلا مصادقة): المسارات، المراحل والأدوات،
/// القوالب، الباقات، وأحدث المحتوى — لقطة واحدة مُكاشة من الخادم.
class PublicRepository {
  PublicRepository(this._api);

  final ApiClient _api;

  Future<Map<String, dynamic>> overview() async {
    final res = await _api.get(ApiEndpoints.publicOverview);
    return res['data'] is Map
        ? Map<String, dynamic>.from(res['data'] as Map)
        : {};
  }
}
