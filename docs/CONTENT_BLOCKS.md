# Content blocks and revision contract

Status: **3.6.0A foundation implemented; admin UI not yet wired.**

`ContentBlock` is the common content primitive for the Admin Experience Studio.
It replaces the legacy product's monolithic page columns with structured,
allow-listed block types. No executable template, JavaScript, iframe or custom
CSS is stored in a block.

## Storage model

- A block has a stable UUID and may be global or use a nullable polymorphic
  `owner` (`owner_type` + `owner_id`).
- `block_type` and `status` are closed enums.
- The initial allow-list covers rich text, hero, CTA, category/listing grids,
  FAQ, image/text, announcement, carousel, gallery, safe video, logo strip and
  testimonials. Each type remains unavailable to the admin UI until its JSON
  schema, validator and renderer ship together.
- `content` is structured JSON. Tiptap documents and the sanitized HTML
  projection will be defined by the 3.6.0B schema validators.
- `version` is an optimistic-lock token, not a display counter.
- `scheduled_for` and `published_at` are present now so scheduled publishing
  does not require a later table rewrite.

## Revision invariants

1. Creation and revision 1 are written in one database transaction.
2. Every real update locks the row, compares the caller's expected version,
   increments it once and appends one full snapshot.
3. An identical re-submit creates neither a version nor a revision.
4. A stale editor fails before any write, preventing lost updates.
5. Revision rows are append-only through Eloquent.
6. Rollback never rewrites history. It copies an older snapshot into a new
   current version and appends a `rolled_back` revision.
7. UUID and polymorphic ownership are recorded in snapshots for audit but are
   immutable during rollback.
8. Hard deletion of a block cascades revisions at the database layer; ordinary
   application deletion is soft-delete.

The first implementation intentionally uses full JSON snapshots. They keep
rollback deterministic and easy to audit. Diff storage is deferred until real
storage measurements show that it is necessary.

## Write boundary

Application code must not update `ContentBlock` directly. Use:

- `CreateContentBlock`
- `UpdateContentBlock`
- `RollbackContentBlock`

Status transitions will be added as a separate closed action in 3.6.0D. This
keeps publish/schedule rules out of controllers and out of the model.

## Next slice

3.6.0B adds the Tiptap document schema, server-side `mews/purifier` profiles,
sanitized render projections and the first admin editor. The package already
contains `mews/purifier`; no second sanitizer dependency is required.

The complete accepted/rejected functional-reference decisions and delivery
order are in [`ADMIN_EXPERIENCE_STUDIO.md`](ADMIN_EXPERIENCE_STUDIO.md).

Caching will use explicit/versioned keys while the production default is the
database cache store. Laravel tagged cache is reserved for a deliberate Redis
deployment, not assumed by this domain.
