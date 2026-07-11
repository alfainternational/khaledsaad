import 'package:get/get.dart';

import '../../features/account/account_page.dart';
import '../../features/agency/branding_page.dart';
import '../../features/approvals/approvals_page.dart';
import '../../features/auth/forgot_password_page.dart';
import '../../features/auth/login_page.dart';
import '../../features/auth/register_page.dart';
import '../../features/billing/billing_page.dart';
import '../../features/clients/clients_page.dart';
import '../../features/dashboard/dashboard_page.dart';
import '../../features/onboarding/onboarding_page.dart';
import '../../features/team/team_page.dart';
import '../../features/projects/brief_page.dart';
import '../../features/projects/execution_package_page.dart';
import '../../features/projects/intelligence_page.dart';
import '../../features/projects/project_detail_page.dart';
import '../../features/projects/project_reports_page.dart';
import '../../features/projects/project_tools_page.dart';
import '../../features/projects/projects_page.dart';
import '../../features/splash/splash_page.dart';
import '../../features/studio/generation_detail_page.dart';
import '../../features/studio/studio_page.dart';
import '../../features/tool_runner/tool_runner_page.dart';
import 'app_routes.dart';

/// سجل صفحات التطبيق.
class AppPages {
  const AppPages._();

  static final routes = <GetPage>[
    GetPage(name: Routes.splash, page: () => const SplashPage()),
    GetPage(name: Routes.login, page: () => const LoginPage()),
    GetPage(name: Routes.register, page: () => const RegisterPage()),
    GetPage(name: Routes.forgotPassword, page: () => const ForgotPasswordPage()),
    GetPage(name: Routes.dashboard, page: () => const DashboardPage()),
    GetPage(name: Routes.onboarding, page: () => const OnboardingPage()),
    GetPage(name: Routes.projects, page: () => const ProjectsPage()),
    GetPage(name: Routes.projectDetail, page: () => const ProjectDetailPage()),
    GetPage(name: Routes.projectTools, page: () => const ProjectToolsPage()),
    GetPage(name: Routes.projectBrief, page: () => const BriefPage()),
    GetPage(name: Routes.projectIntelligence, page: () => const IntelligencePage()),
    GetPage(name: Routes.projectReports, page: () => const ProjectReportsPage()),
    GetPage(name: Routes.executionPackage, page: () => const ExecutionPackagePage()),
    GetPage(name: Routes.toolRunner, page: () => const ToolRunnerPage()),
    GetPage(name: Routes.studio, page: () => const StudioPage()),
    GetPage(name: Routes.studioGeneration, page: () => const GenerationDetailPage()),
    GetPage(name: Routes.team, page: () => const TeamPage()),
    GetPage(name: Routes.approvals, page: () => const ApprovalsPage()),
    GetPage(name: Routes.account, page: () => const AccountPage()),
    GetPage(name: Routes.clients, page: () => const ClientsPage()),
    GetPage(name: Routes.agencyBranding, page: () => const BrandingPage()),
    GetPage(name: Routes.billing, page: () => const BillingPage()),
  ];
}
