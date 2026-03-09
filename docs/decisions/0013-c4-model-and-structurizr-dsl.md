# 13. C4 Model + Structurizr DSL for Architecture Documentation


Date: 2026-03-05

## Status

Accepted

## Context

Architecture diagrams created in draw.io, Visio, or similar tools tend to drift from the actual system over time: they live outside the codebase, have no validation, and cannot be reviewed in a pull request. The team needs a documentation-as-code approach that:

1. Keeps architecture diagrams **versioned alongside the source code**.
2. Allows diagrams to be **reviewed in pull requests** (text diff of the model).
3. Provides a **consistent hierarchy** of views (context → containers → components) suited to the C4 model notation.
4. Can be rendered locally without internet access or a paid SaaS account.

Options considered:

| Tool | Format | Rendering | Validation | Notes |
|---|---|---|---|---|
| draw.io XML | XML | draw.io app | None | Binary-like XML; poor diffs; no C4 support natively |
| PlantUML | DSL | plantuml.jar or server | Syntax only | C4 plugin available; verbose for large models |
| Mermaid | Markdown-embedded | Browser / VS Code | Syntax only | No native C4 support; limited to flowcharts for C4 approximation; no cross-view validation |
| **Structurizr DSL** | Text DSL | Structurizr Lite (Docker) | Full C4 inspection (100+ rules) | First-class C4 model; relationships validated; views generated from a single model |

## Decision

Use the **Structurizr DSL** (`workspace.dsl`) as the authoritative architecture model, rendered by **Structurizr Lite** (Docker) for local browsing.

- The entire C4 model (all elements, relationships, and views) is declared in a single `workspace.dsl` file at the repository root.
- Views defined:
  - **System Context (Level 1)** — service in relation to its users and external systems.
  - **Container Diagram (Level 2)** — internal containers: Hyperf API, Crontab Worker, MySQL, Redis.
  - **Component Diagram (Level 3) — Hyperf API** — expands the API container into middlewares, controllers, use cases, domain model, and infrastructure adapters.
  - **Component Diagram (Level 3) — MySQL** — expands the MySQL container into its three tables.
  - **Component Diagram (Level 3) — Crontab Worker** — expands the Crontab Worker.
  - **Dynamic Views** — four sequence-style views for the main flows: immediate withdrawal, scheduled withdrawal, idempotency cache hit, rate-limit exceeded, and crontab processing.
- `configuration { scope softwareSystem }` enforces that every component belongs to a container, every relationship has a technology string, and all components are included in at least one view.
- `!docs .` links the workspace root (README.md) as the system documentation.
- `!decisions docs/decisions/` links ADR files to the Structurizr decisions panel.

Structurizr Lite is run locally via:
```bash
docker run -it --rm -p 8080:8080 -v $(pwd):/usr/local/structurizr structurizr/lite
```

In addition, **Mermaid diagrams** embedded in `README.md` provide a lightweight, zero-dependency rendering path for GitHub/GitLab README viewers — these are manually kept in sync with the DSL model.

## Consequences

**Positive:**
- The DSL is plain text; architecture changes are code-reviewed like any source file.
- Structurizr's inspection engine validates the model against C4 rules (missing technologies, disconnected elements, undocumented components) and reports errors before rendering.
- A single model produces multiple consistent views; renaming a component updates all views simultaneously.
- ADR integration in Structurizr links architectural decisions directly to the diagram elements they affect.
- Structurizr Lite is free and runs entirely offline.

**Negative / Risks:**
- Mermaid diagrams in README.md are **manually maintained** and can drift from the DSL model. A future improvement would be to auto-generate Mermaid from the DSL (e.g., via Structurizr CLI export).
- Structurizr DSL syntax is specific to Structurizr; team members must learn the format. The community edition (Lite) does not support collaboration features.
- `autoLayout` produces non-deterministic element positioning across Structurizr Lite restarts. Manual element position overrides require JSON workspace format, which is harder to maintain in version control.
