# demo

No demo exists yet. A meaningful demo needs either (a) a real replacement `view` plugged into
`FormBuilder` (see `docs/PUBLIC_API.md`), which doesn't exist until a redesign builds one, or
(b) a mocked WordPress adapter for exercising `src/state`/`src/serialization` in isolation. For
(b), see `tests/unit/FormBuilder.test.mjs`'s `makeFakeFetch`/`makeAdapter` helpers — that's
effectively the smallest possible "demo," just written as tests rather than an interactive page.
