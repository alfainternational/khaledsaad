# Projectless contextual learning implementation plan

1. Add failing tests for a projectless entitled run, entitlement denial,
   compatibility redirects, and preserved attempts.
2. Add a forward-only migration for workspace-owned learning runs and implement
   the run resolver plus `learning.marketing` entitlement.
3. Move canonical learning routes/controller flow off `Project`, keep legacy
   route redirects, and update views/recommender/prefill/evaluator for optional
   project context.
4. Add failing tests for lesson-to-exercise links and exact contextual help.
5. Extend `LearningPresenter`, embed applications in the public lesson, and add
   a structured lesson-context AI helper that honours budget and hypothesis
   labelling.
6. Add an admin draft-only lesson-refresh service with source evidence and
   coverage tests.
7. Add failing layout contract tests; introduce the single `1cm` gutter token,
   remove nested outer caps, and rebuild the lesson hero/reading/application
   layout.
8. Run focused tests, asset build, view cache clear, and desktop/mobile visual
   verification. Fix regressions before deployment.
9. Deploy only the scoped files and migration, run migration/cache/build steps,
   then verify production slowly to avoid the known firewall rate limit.
