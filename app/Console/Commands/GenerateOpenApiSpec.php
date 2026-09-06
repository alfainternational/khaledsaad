<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

/**
 * يولّد مواصفة `api/v1` من جدول المسارات نفسه.
 *
 * **لماذا مولَّدة لا مكتوبة:** الوثيقة اليدوية تتعفّن. تُكتب صحيحةً مرة،
 * ثم يضيف أحدهم نقطة نهاية ولا يحدّثها، فتصير أسوأ من غيابها — لأن من
 * يقرأها يثق بها. المولَّدة من الكود لا تكذب إلا إذا كذب الكود.
 *
 * ولها استعمال ثانٍ أهم: `--check` في CI يفشل عند أي تغيير غير مُعلَن في
 * العقد. ولمّا كان التطبيق يقرأ العقد نفسه، فكسرُه صامتًا يعني تطبيقًا
 * يتعطّل في يد المستخدم بعد نشرٍ بدا ناجحًا.
 */
class GenerateOpenApiSpec extends Command
{
    protected $signature = 'api:openapi {--check : يفشل إن كانت المواصفة لا تطابق المسارات}';

    protected $description = 'يولّد docs/api/openapi.json من مسارات api/v1';

    public function handle(): int
    {
        $spec = $this->build();

        /*
         * JSON لا YAML: كلاهما صيغةٌ رسمية لـOpenAPI وتقرؤهما الأدوات
         * نفسها، وJSON لا يضيف اعتمادًا على حزمة خارجية لمجرد التنسيق.
         * والمقارنة الحرفية في `--check` أدقّ بلا مسافات بادئة تختلف.
         */
        $yaml = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
        $path = base_path('docs/api/openapi.json');

        if ($this->option('check')) {
            if (! is_file($path) || file_get_contents($path) !== $yaml) {
                $this->error('مواصفة العقد لا تطابق المسارات. شغّل: php artisan api:openapi');

                return self::FAILURE;
            }

            $this->info('المواصفة مطابقة للمسارات.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $yaml);
        $this->info('كُتبت المواصفة: docs/api/openapi.json');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $paths = [];

        foreach ($this->apiRoutes() as $route) {
            $uri = '/'.ltrim($route->uri(), '/');
            $uri = (string) preg_replace('/\{(\w+)\??\}/', '{$1}', $uri);

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $paths[$uri][strtolower($method)] = [
                    'operationId' => $route->getName() ?? Str::slug($method.'-'.$uri),
                    'summary' => $route->getName() ?? $uri,
                    'tags' => [$this->tag($route)],
                    'security' => $this->isPublic($route) ? [] : [['sanctum' => []]],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/Success'],
                        '402' => ['$ref' => '#/components/responses/Error'],
                        '503' => ['$ref' => '#/components/responses/Error'],
                    ],
                ];
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'خالد سعد — عقد api/v1',
                'version' => '1.0.0',
                'description' => implode("\n", [
                    'مولَّدة من جدول المسارات — لا تُحرَّر يدويًّا.',
                    '',
                    'إصدارٌ واحد لا ثانٍ: يتطوّر api/v1 في مكانه، محروسًا ببوابة',
                    'حدّ أدنى لإصدار التطبيق تُشحن قبل أي تغيير في العقد.',
                ]),
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/').'/api']],
            'components' => $this->components(),
            'paths' => $paths,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function components(): array
    {
        return [
            'securitySchemes' => [
                'sanctum' => ['type' => 'http', 'scheme' => 'bearer'],
            ],
            'schemas' => [
                /*
                 * ظرف الخطأ هو ما يجعل INV-8 يسري على الويب والتطبيق معًا.
                 * `kind` هو الحقل الحاسم: `ours` يعني عطلًا لدينا، ويأتي
                 * دائمًا بـ`user_action = null` — فلا يخترع العميل مطالبةً
                 * للمستخدم على عطلٍ ليس منه.
                 */
                'Error' => [
                    'type' => 'object',
                    'required' => ['error'],
                    'properties' => [
                        'error' => [
                            'type' => 'object',
                            'required' => ['kind', 'code', 'title', 'message', 'user_action'],
                            'properties' => [
                                'kind' => ['type' => 'string', 'enum' => ['ours', 'theirs', 'input']],
                                'code' => ['type' => 'string'],
                                'title' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'user_action' => [
                                    'nullable' => true,
                                    'description' => 'يجب أن يكون null حين kind = ours.',
                                    'type' => 'object',
                                    'properties' => [
                                        'label' => ['type' => 'string'],
                                        'route' => ['type' => 'string', 'nullable' => true],
                                    ],
                                ],
                                'retry_after' => ['type' => 'integer', 'nullable' => true],
                            ],
                        ],
                    ],
                ],
                'Envelope' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => [
                        'data' => ['type' => 'object'],
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'locale' => ['type' => 'string'],
                                'server_time' => ['type' => 'string', 'format' => 'date-time'],
                            ],
                        ],
                    ],
                ],
                /*
                 * كل قيمة نطاق تُرسَل كزوج: العميل يعرض `label` ويفرّع على
                 * `value`. هذا ما يجعل «لا قيم خام في الواجهة» مستحيل الخرق
                 * من العميل بدل أن يكون قاعدةً يتذكّرها من يبنيه.
                 */
                'LabelledValue' => [
                    'type' => 'object',
                    'required' => ['value', 'label'],
                    'properties' => [
                        'value' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                ],
            ],
            'responses' => [
                'Success' => [
                    'description' => 'نجاح',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Envelope']]],
                ],
                'Error' => [
                    'description' => 'عطل مصنَّف',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
                ],
            ],
        ];
    }

    /**
     * @return array<int, Route>
     */
    private function apiRoutes(): array
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/'))
            ->values()
            ->all();

        usort($routes, fn (Route $a, Route $b) => $a->uri() <=> $b->uri());

        return $routes;
    }

    private function tag(Route $route): string
    {
        $segments = explode('/', $route->uri());

        return $segments[2] ?? 'v1';
    }

    /**
     * العامّ هو ما لا يمرّ بحارس المصادقة — يُقرأ من الوسائط لا من قائمة
     * مكتوبة، وإلا تخلّفت القائمة عن المسارات فادّعت حمايةً لا وجود لها.
     */
    private function isPublic(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'auth')) {
                return false;
            }
        }

        return true;
    }
}
