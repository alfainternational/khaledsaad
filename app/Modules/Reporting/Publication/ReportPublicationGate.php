<?php

namespace App\Modules\Reporting\Publication;

use App\Models\Report;
use App\Models\User;
use App\Modules\Reporting\Validation\SemanticValidator;
use App\Modules\Reporting\Validation\ValidationViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportPublicationGate
{
    public function __construct(
        private readonly ReportContractAssembler $assembler,
        private readonly SemanticValidator $validator,
    ) {}

    /** @param callable(array<string,mixed>,array<int,array<string,mixed>>):array<string,mixed>|null $repair */
    public function publish(Report $report, ?callable $repair = null): Report
    {
        $result = DB::transaction(function () use ($report, $repair): array {
            $payload = $this->assembler->assemble($report);
            $validation = $this->validator->validate($payload);

            if (! $validation->passes() && $repair !== null && $report->provenance === Provenance::Automated->value) {
                $payload = $repair($payload, $validation->toArray());
                $validation = $this->validator->validate($payload);
            }

            if (! $validation->passes() && $report->provenance === Provenance::Automated->value) {
                $this->degradeIsolatableRecommendations($report, $validation->blocking());
                $payload = $this->assembler->assemble($report->refresh());
                $validation = $this->validator->validate($payload);
            }

            $report->validationFindings()->delete();
            foreach ($validation->violations as $violation) {
                $this->persist($report, $violation);
            }

            if (! $validation->passes()) {
                $report->forceFill(['validation_status' => 'failed', 'status' => 'draft'])->save();

                return [
                    'report' => $report->refresh(),
                    'error' => collect($validation->blocking())
                        ->map(fn ($item) => "{$item->code}: {$item->message}")
                        ->implode(' | '),
                ];
            }

            $report->forceFill([
                'validation_status' => $validation->violations === [] ? 'passed' : 'passed_with_warnings',
                'contract_payload' => $payload,
                'schema_version' => 2,
                'status' => 'published',
                'published_at' => now(),
                'issued_at' => now(),
            ])->save();

            return ['report' => $report->refresh(), 'error' => null];
        });

        if ($result['error'] !== null) {
            throw ValidationException::withMessages(['report' => $result['error']]);
        }

        return $result['report'];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    public function publishHybrid(Report $report, User $actor, array $before, array $after, string $reason): Report
    {
        $report->forceFill([
            'provenance' => Provenance::Hybrid->value,
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ])->save();
        $report->revisions()->create([
            'actor_type' => User::class,
            'actor_id' => $actor->id,
            'diff' => ['before' => $before, 'after' => $after],
            'reason' => $reason,
        ]);
        $report->humanTraces()->create([
            'type' => 'override',
            'body' => $reason,
            'meta' => ['changed_paths' => array_values(array_unique([...array_keys($before), ...array_keys($after)]))],
            'created_by' => $actor->id,
        ]);

        return $this->publish($report);
    }

    /** @param array<int, ValidationViolation> $violations */
    private function degradeIsolatableRecommendations(Report $report, array $violations): void
    {
        $isolatable = ['R01', 'R02', 'R03', 'R04', 'R05', 'R06', 'R07', 'R08', 'R14', 'R15'];

        foreach ($violations as $violation) {
            if (! in_array($violation->code, $isolatable, true)
                || ! preg_match('/^findings\.(\d+)\.recommendation/', $violation->path, $matches)) {
                continue;
            }

            $finding = $report->findings->values()->get((int) $matches[1]);
            $recommendation = $finding?->recommendations->first();
            if ($recommendation === null) {
                continue;
            }

            $reasons = array_filter(explode(',', (string) $recommendation->degrade_reason));
            $reasons[] = $violation->code;
            $recommendation->forceFill([
                'degraded' => true,
                'degrade_reason' => implode(',', array_values(array_unique($reasons))),
            ])->save();
        }
    }

    private function persist(Report $report, ValidationViolation $violation): void
    {
        $report->validationFindings()->create([
            'rule_code' => $violation->code,
            'severity' => $violation->severity,
            'path' => $violation->path,
            'message' => $violation->message,
            'suggested_action' => $violation->suggestedAction,
            'meta' => $violation->meta,
        ]);
    }
}
