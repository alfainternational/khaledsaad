import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../core/network/api_client.dart';
import '../../data/repositories/ai_assist_repository.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/billing_repository.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/repositories/lifecycle_repository.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/repositories/public_repository.dart';
import '../../data/repositories/studio_repository.dart';
import '../../data/repositories/tool_repository.dart';
import '../../data/repositories/workspace_repository.dart';
import '../../data/services/session_service.dart';
import '../../data/services/workspace_service.dart';

/// حقن التبعيات العام: ApiClient + المستودعات.
/// (SessionService و Dio مُسجّلان مسبقاً في main كخدمات دائمة.)
class InitialBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<ApiClient>(() => ApiClient(Get.find<Dio>()), fenix: true);

    Get.lazyPut<AuthRepository>(() => AuthRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<PublicRepository>(
        () => PublicRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<WorkspaceRepository>(
        () => WorkspaceRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<ProjectRepository>(
        () => ProjectRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<ToolRepository>(() => ToolRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<StudioRepository>(() => StudioRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<AiAssistRepository>(
        () => AiAssistRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<LifecycleRepository>(
        () => LifecycleRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<CollabRepository>(
        () => CollabRepository(Get.find<ApiClient>()), fenix: true);
    Get.lazyPut<BillingRepository>(
        () => BillingRepository(Get.find<ApiClient>()), fenix: true);

    Get.lazyPut<WorkspaceService>(
      () => WorkspaceService(Get.find<WorkspaceRepository>(), Get.find<SessionService>()),
      fenix: true,
    );
  }
}
