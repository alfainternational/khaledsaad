<?php

namespace App\Providers;

use App\Contracts\AiGatewayInterface;
use App\Application\Integration\CloudIntegrationService;
use App\Contracts\CloudClientContract;
use App\Domain\Integration\Services\CloudIntegrationGate;
use App\Domain\Integration\Services\HttpCloudClient;
use App\Domain\Integration\Services\NullCloudClient;
use App\Domain\AI\Services\FallbackAiGateway;
use App\Domain\AI\Services\GeminiGateway;
use App\Domain\AI\Services\NvidiaNimGateway;
use App\Domain\Approval\Models\Approval;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Client\Models\Client;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Policies\ApprovalPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\WorkspaceInvitationPolicy;
use App\Policies\WorkspaceMemberPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EntitlementResolver::class);
        $this->app->singleton(FeatureFlagService::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(AiGatewayInterface::class, function () {
            $provider = config('services.ai.provider', 'gemini');

            return match ($provider) {
                'nvidia' => new NvidiaNimGateway,
                'fallback' => new FallbackAiGateway(
                    new GeminiGateway,
                    new NvidiaNimGateway,
                ),
                default => new GeminiGateway,
            };
        });

        $this->app->singleton(HttpCloudClient::class);

        $this->app->singleton(CloudClientContract::class, function ($app) {
            $http = $app->make(HttpCloudClient::class);

            return $http->configured() ? $http : new NullCloudClient;
        });

        $this->app->singleton(CloudIntegrationGate::class);
        $this->app->singleton(CloudIntegrationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Approval::class, ApprovalPolicy::class);
        Gate::policy(WorkspaceInvitation::class, WorkspaceInvitationPolicy::class);
        Gate::policy(WorkspaceMember::class, WorkspaceMemberPolicy::class);

        Blade::if('feature', fn (string $key): bool => feature($key));
        Blade::directive('entitlement', fn (string $expression): string => "<?php echo e(entitlement($expression)); ?>");
    }
}
