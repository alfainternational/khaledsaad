# Reader-Focused Public Copy Design

## Goal

Remove public-facing copy that explains internal publishing, administration, data sourcing, or independence from third-party platforms when that information does not help a visitor decide, learn, or act.

## Scope

The audit covers public marketing and content-library views. Admin views, application workspaces after login, legal disclosures, privacy/security explanations, service-status pages, and truthful availability labels remain unchanged because their operational detail is necessary to the person reading them.

The current affected copy is limited to the homepage content section and the public content-library index:

- References to content being "inside the platform" or published "here directly".
- Comparison with LinkedIn or external platforms.
- Empty states that mention publishing from the admin panel.

## Copy rule

Every replacement must answer at least one reader question: what will I learn, how will I apply it, or what should I do next? It must not promise content that does not exist. Empty states should be honest while offering a useful next action.

## Replacement direction

- Homepage section: position the library as practical material for understanding marketing problems and turning ideas into steps.
- Library hero: explain that articles, lessons, lectures, and courses support clearer decisions and practical execution.
- Empty states: invite the visitor to start the free diagnosis when no suitable published material exists; do not mention the admin panel or publishing workflow.

## Regression protection

Feature tests render the homepage and library and reject the known implementation-centered phrases. The same tests assert the new benefit-led headings and calls to action.
