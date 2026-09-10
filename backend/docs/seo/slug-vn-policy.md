# Vietnamese slug policy (`vn-v1`)

## Canonical form

1. Apply Unicode NFKC/NFD when ICU is available.
2. Map `đ/Đ` to `d/D`, remove combining marks, transliterate to ASCII, and
   lowercase.
3. Convert every non `[a-z0-9]` run to one hyphen and trim hyphens.
4. Reject an empty result and values longer than 160 ASCII characters.
5. An editor-provided slug is validated, never silently rewritten. It must be
   one lowercase ASCII path segment with no URL, query, fragment, whitespace,
   control character, or reserved route name.

## Ownership

The policy is implemented by `VietnameseSlugNormalizer` and must be used by
admin CRUD, CSV imports, KIOT creates, AI-created posts, seeders, and the SEO
planning command. A public slug is locked after creation; a later name update
does not silently rename it.

## Redirect rule

When a slug or primary category changes, the old verified path is recorded in
`slug_histories` and redirects once to the current canonical path. The resolver
stores the entity id so aliases cannot be accidentally reassigned.
