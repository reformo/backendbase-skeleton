# Portable Skill Authoring

Skills under `resources/skills` are reusable code-generation guides for other projects. This repository provides verified examples, not literal templates.

When creating or updating a skill:

- Inspect current source, configuration, tests, and `resources/platform` before describing a pattern.
- Require discovery of unknown target-project facts needed by the task, including affected architecture, namespaces, paths, dependencies, contracts, security, tests, and deployment.
- Adapt every instruction to the target project. Do not make target code depend on this repository.
- Treat Backendbase class names, paths, API names, schemas, headers, environment keys, hosts, credentials, fixtures, and sample data as role examples only.
- Keep repository-specific details in references as provenance or current limitations. Mark exact Backendbase rules as conditional on an unmodified Backendbase project.
- Keep each skill self-contained.
- Prefer current code and tests over older narrative documentation when they conflict. Record important drift instead of copying it.
- Forward-test the skill against a differently named target project. Remove hidden Backendbase assumptions before completion.
