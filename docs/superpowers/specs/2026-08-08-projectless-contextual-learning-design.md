# Projectless contextual marketing learning

## Goal

Make the existing twenty-lesson marketing course usable according to its access
and plan entitlement without requiring a project, connect every lesson directly
to its exercises, and make AI help specific to the exact lesson section and
question being answered.

## Product rules

- The course remains one feature inside the existing platform. No parallel
  academy, duplicate catalog, or hidden project is introduced.
- Published lesson visibility continues to obey `ContentAccessService`.
- Interactive learning requires an authenticated workspace and the
  `learning.marketing` entitlement, not a project.
- A project is optional context. Existing project-bound learning data and URLs
  remain usable and are migrated/redirected without deletion.
- AI suggestions are always labelled as hypotheses, consume the workspace AI
  budget before invocation, and never save themselves as learner answers.
- AI lesson refresh creates an editorial draft with evidence; it never publishes
  automatically.

## Data and ownership

`marketing_learning_runs` gains `workspace_id`; `project_id` becomes nullable.
The canonical run is owned by one workspace and starter. Existing rows retain
their project and derive their workspace during migration. New rows can exist
with no project. Attempts continue to belong to the run, preserving history.

## Routes

Canonical routes live under `/app/learn/marketing` and do not contain a project:

- course index;
- exercise form/save/review/result/retry;
- contextual help for one exercise question.

The old `/app/projects/{project}/learn/marketing/...` routes remain as
compatibility redirects and may attach that owned project as optional context.

## Lesson-to-application bridge

`LearningPresenter` maps a published marketing lesson by `learning_order` and
`source_key` to the same `MarketingCourseCatalog`. The public lesson page shows
its exact exercises after the lesson body. Authenticated entitled users enter
the exercise directly; guests are sent through login and return to the exercise.

## Contextual AI help

The contextual helper receives a server-built `MarketingLessonContext` only:

- full current lesson body as readable text;
- lesson title, outline, order, and catalog version;
- exact exercise purpose, deliverable, question, rubric, help, and example;
- short titles/summaries of adjacent lessons;
- the learner's current and prior answers;
- optional project Brain facts when the learner explicitly selected a project.

The model returns structured Arabic: explanation for this exact field, one
relevant example, why it fits the lesson, and one next action. The prompt rejects
generic marketing advice and requires naming the lesson concept used. Responses
are cached by lesson content revision, exercise, question, learner context, and
optional project context.

## AI-assisted lesson updates

An admin-only draft service can read the current lesson, its catalog definition,
and supplied trusted update sources, then generate a proposed diff. The draft
stores sources and remains unpublished until an admin approves it through the
existing content editor.

## Layout

- One semantic token equals CSS `1cm` and is the only outer inline gutter for
  public, authenticated, workspace, admin, report, and learning surfaces.
- Remove nested viewport-width/max-width wrappers that add outer whitespace.
- Keep readable line length on prose paragraphs, not on the whole page/grid.
- Desktop lesson layout uses the full remaining width: a fixed reading map and a
  fluid main column. Exercises use a wide split layout where space allows.
- Hero copy and cover occupy separate grid areas; neither overlays the other.
- RTL and Latin digits `0-9` remain enforced.

## Verification

- Feature tests prove an entitled user with no project can complete learning.
- Feature tests prove access is denied when the entitlement is disabled.
- Existing project URLs preserve access through redirects.
- Public lesson tests prove direct exercise links are present and exact.
- Context tests prove full lesson/section/question context reaches the AI layer
  and generic context is rejected/falls back safely.
- CSS tests prove every shell consumes the `1cm` token and the learning grid no
  longer carries the previous `48rem`/`1180px` page caps.
- Build, focused PHP tests, and desktop/mobile screenshots must pass before
  deployment.
