# AB2TI 2.0 — Post-RC verification

Canonical immutable RC baseline: `0037d7ce7451b57d805afa04a2ee064e8e326fb5`  
Tag: `ab2ti-2.0-rc` (not modified)

Phases 4, 5A, and 5B remain CLOSED.

---

## Publishing

Finding: missing optional YouTube video support  
Severity: S2  
Result: FIXED  
Regression coverage: `YouTubeVideoTest` (supported watch / youtu.be / shorts / live / embed; rejection of non-YouTube, lookalike hosts, unsafe schemes); `PublishingYouTubeTest` (create without URL, create/update across types, invalid replacement, clearing, public `youtube-nocookie` embed payload, no-video page, unpublished remains private). Full suite after hotfix: Tests 496, Assertions 4382, Skipped 5 (pre-hotfix 459 / 4238 / 5).
