# REGULATORY RULEBOOK — AJM PLATFORM

**Project:** CV Abadi Jaya Mitra (AJM) — B2B / B2G Supplier-Side Procurement Platform
**Repository:** `D:\laravel\pengadaan-barang-ajm`
**Primary source of truth:** `docs/REGULATORY_COMPLIANCE_AUDIT.md` (v1.0, FINAL, `YES WITH CONDITIONS`, QC recommendation `APPROVED FOR RULEBOOK — SUBJECT TO CONDITIONS`)
**Rulebook version:** 1.0
**Derivation rule:** Every rule in this document is transcribed from the approved audit. Nothing is re-interpreted, silently upgraded, or invented. Uncertainties are preserved as recorded.

---

# 0. PURPOSE & AUTHORITY

This Rulebook is the authoritative implementation bridge for the AJM platform:

```
INDONESIAN REGULATION
        ↓
LEGAL REQUIREMENT
        ↓
REGULATORY RULE
        ↓
BUSINESS RULE
        ↓
SYSTEM BEHAVIOR
        ↓
AUTOMATION
        ↓
TEST
        ↓
AUDIT EVIDENCE
```

It answers, for every material rule: what applies, why, to whom/what, when, what triggers it, what the system must (and must NOT) do, whether automation is deterministic, whether human review or professional legal/tax review remains necessary, which module/component is affected, how it is tested, and what happens when the regulation changes.

**Authority hierarchy:**
1. `docs/REGULATORY_COMPLIANCE_AUDIT.md` — primary source of truth (approved 14-Aug-2026).
2. This Rulebook — derived implementation spec. Conflicts with the audit must be resolved in favor of the audit and recorded via `RULE-CONFLICT-*`.
3. Module-level design documents — only after this Rulebook is reviewed.

This is **NOT a legal-compliance certification**. Real-world compliance is `UNVERIFIED` unless AJM evidence exists.

---

# 1. RULEBOOK GOVERNANCE

- **Owner:** Compliance / Legal reviewer (capability, `ROLE-04`).
- **Change procedure:** No rule change is implemented directly in code. Every change follows §20 (Regulatory Change Management) and is recorded in §25 (Rulebook Change Log).
- **Versioning:** Each rule carries `rule_version`, `effective_from`, `effective_until`, `source_version`, `verification_date`. Historical meaning is never overwritten; transactions must preserve the resolved rule snapshot used at transaction time (§20, §10 for tax).
- **Review trigger:** Annual re-verification of all `CONFIRMED` citations, or trigger-based on a tracked watch item (§20).
- **Normalization:** Rules that cannot be normalized cleanly are listed in §25 with the reason and retained as recorded in the audit.

---

# 2. STATUS & CONFIDENCE MODEL

Every material rule carries **three independent status dimensions** that must never be conflated:

| Column | Meaning | Allowed values |
|---|---|---|
| **Regulatory Applicability** | What the law requires of AJM as a business/PSE | `REQUIRED`, `CONDITIONALLY REQUIRED`, `RECOMMENDED`, `NOT APPLICABLE`, `UNCERTAIN / LEGAL REVIEW` |
| **System Readiness** | What the current platform (Module 1–8) implements | `IMPLEMENTED`, `PARTIAL`, `MISSING`, `NOT APPLICABLE`, `UNVERIFIED` |
| **Real-World Compliance** | AJM's actual operational/legal status | `COMPLIANT` (evidence exists), `NON-COMPLIANT` (evidence exists), `UNVERIFIED`, `NOT APPLICABLE` |

**Critical rules:**
- `System Readiness = MISSING` does **NOT** imply `Real-World Compliance = NON-COMPLIANT`. The codebase cannot prove real-world legal status.
- A missing platform feature does not automatically mean legal non-compliance; absent real-world evidence, use `UNVERIFIED`.
- Uncertainty is never resolved by guessing. It is preserved as `REQUIRES HUMAN REVIEW` or `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW`.
- Conflicts between audit findings are recorded as `RULE-CONFLICT-XXX` with provisional `REQUIRE_REVIEW`, never silently resolved.

**Confidence values:** `CONFIRMED` (primary source verified), `UNCERTAIN` (basis unresolved), `REQUIRES FURTHER VERIFICATION` (implementation detail), `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW`.

---

# 3. RULE ID CONVENTION

- Rule IDs are **globally unique, immutable, stable, traceable** identifiers — not legal article numbers.
- Existing Rule IDs from the audit are **preserved verbatim**. No renumbering.
- Convention: `<DOMAIN>-<NUMBER>` (e.g., `GOV-01`, `PSE-CERT-001`, `TAX-PPN-02`). Domain prefixes follow the audit inventory.
- One business behavior may depend on multiple legal provisions: keep one Rule ID and reference all applicable provisions (no unnecessary duplicate rules).
- Dependency types: `depends_on`, `affects`, `conflicts_with`, `supersedes`.
- Conflict identifiers: `RULE-CONFLICT-001`, `RULE-CONFLICT-002`, ... (provisional, `REQUIRE_REVIEW`).

**Global uniqueness:** verified in Appendix A (98 discrete Rule IDs, zero duplicates).

---

# 4. GOVERNMENT PROCUREMENT RULES

Domain: Government Procurement (Cluster A). Rule IDs: `GOV-*`.

**Core positioning:** AJM is a **supplier** participating in government procurement. The platform is a supplier-side support tool and internal document mirror. AJM does not operate an official procurement system (SIKAP/e-Procurement belongs to LKPP/KPB).

**AJM INTERNAL RECORD vs OFFICIAL GOVERNMENT PROCUREMENT RECORD** — the Rulebook prohibits treating:
- AJM `ORD-*` as an official government PO;
- AJM's private portal as the official government procurement system;
- internal mirrored records as the original government instrument;
- unverified catalog references as valid official catalog records.

This does **not** prohibit legitimate supplier-side commercial or fulfillment support (the audit does not establish such a prohibition).

## GOV-01 — Document Labelling (Internal Mirror vs Official Instrument)

| Field | Value |
|---|---|
| Rule ID | GOV-01 |
| Rule Name | Document labelling: platform documents are internal mirrors, not official procurement instruments |
| Domain | Government Procurement |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL (PDF engine exists; no labelling enforcement) |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018 jo 46/2025 (general) |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Applicable Entity | AJM platform document engine |
| Applicable Transaction | B2G document flows (RFQ/order/BAST/invoice PDFs) |
| Preconditions | Document generation or presentation |
| Exceptions | None recorded |
| Trigger | Any platform-generated document is displayed, sent, or printed |
| Required Behavior | (a) Legal/procurement integrity: never present `ORD-*` or platform documents as official procurement instruments. (b) AJM safeguard: label documents as internal mirrors and carry an official-instrument disclaimer — the specific disclaimer wording is AJM policy, not text mandated by Perpres |
| Forbidden Behavior | Present `ORD-*` or platform documents as official procurement instruments |
| Output | Labelled documents / disclaimer on templates |
| State Change | Document template/labelling metadata applied |
| Required Evidence | Labelled document samples |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (template approval is human) |
| Enforcement | REQUIRE_REVIEW (before template publication) |
| Module | 7 (Documents) |
| System Impact | Module 7 PDF engine, document templates, labelling interceptor (§21 mapping; §24.2-B6) |
| Testing | PROPOSED — feature test: generated PDFs carry label + disclaimer |
| Dependencies | depends_on: DOC-03; affects: PROH-01, PROH-04 |

## GOV-02 — Supplier Eligibility Tracking

| Field | Value |
|---|---|
| Rule ID | GOV-02 |
| Rule Name | Supplier eligibility tracking (NIB, NPWP, KBLI) |
| Domain | Government Procurement |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018 jo 46/2025 |
| Article | Ps 4(2) |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Applicable Entity | AJM supplier company records |
| Preconditions | Company is on-boarded as a supplier |
| Trigger | Supplier record created/updated |
| Required Behavior | Capture and maintain eligibility evidence (NIB, NPWP, KBLI) |
| Forbidden Behavior | Claim eligibility without recorded evidence |
| Output | Supplier eligibility record |
| Required Evidence | Eligibility record with documents |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (document authenticity review) |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| System Impact | Supplier-eligibility service (§24.2-B8); `supplier_eligibility` entity |
| Testing | PROPOSED — feature test: supplier with missing NIB flagged |
| Dependencies | affects: PMSE-01, PMSE-02, PROH-03 |

## GOV-03 — Multiple Procurement Method Support

| Field | Value |
|---|---|
| Rule ID | GOV-03 |
| Rule Name | Support multiple procurement methods (tender, direct procurement, e-purchasing, selection) |
| Domain | Government Procurement |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018 jo 46/2025 |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Preconditions | Method-dependent workflow selection |
| Exceptions | None recorded |
| Required Behavior | Procurement-channel abstraction: method, channel (e-catalog vs direct), catalog-status field, warnings |
| Forbidden Behavior | Assume a single procurement method; blanket-prohibit any legitimate method |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (PPK assessment nuance) |
| Enforcement | WARN |
| Module | 2 (Orders) |
| System Impact | Procurement-channel abstraction layer (§24.3-C6) |
| Testing | PROPOSED — feature test: each method maps to a workflow |
| Dependencies | affects: LKPP-01, LKPP-03, LKPP-06, PROH-02 |

## GOV-04 — Do Not Present Internal Status as Official Award Status

| Field | Value |
|---|---|
| Rule ID | GOV-04 |
| Rule Name | Internal order status ≠ official award status |
| Domain | Government Procurement |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018 |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Distinguish internal order/status display from official award/selection status |
| Forbidden Behavior | Present internal order status as official award status |
| Automation Class | DETERMINISTIC (labelling) + HUMAN REVIEW (content approval) |
| Enforcement | REQUIRE_REVIEW |
| Module | 2 (Orders) |
| Testing | PROPOSED — feature test: status labels distinguish internal vs official |
| Dependencies | affects: GOV-01, PROH-01 |

## GOV-05 — System Boundary / No Liability for Official Procurement Outcomes

| Field | Value |
|---|---|
| Rule ID | GOV-05 |
| Rule Name | No liability for official procurement outcomes (system boundary) |
| Domain | Government Procurement |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | General (contractual) |
| Article | N/A |
| Official Source | N/A (platform ToS) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (as recommended ToS clause) |
| Required Behavior | EULA/ToS boundary clause: AJM is a supplier-side support tool |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | LOG_ONLY |
| Module | 0 (Config/Platform) |
| Testing | PROPOSED — content review of ToS |
| Dependencies | affects: GOV-01, B2B-05 |

---

# 5. LKPP / INAPROC / E-CATALOG RULES

Domain: LKPP / INAPROC / E-Catalog (Cluster B). Rule IDs: `LKPP-*`.

**Regulatory frame (verbatim from audit §6.1):**
- **Perpres 16/2018 jo 12/2021 jo 46/2025** — umbrella government procurement regulation.
- **Perpres 17/2023** — INAPROC (Integrated National Procurement; Percepatan Transformasi Digital Pengadaan, dikembangkan LKPP + PT Telkom). **This is the legal basis for INAPROC.**
- Note: "Kepka LKPP 21/2018" is the **Jadwal Retensi Arsip LKPP** (archival-retention schedule), NOT INAPROC; **revoked by Peraturan LKPP 6/2023**.
- **Kepka LKPP 122/2022** — e-Purchasing (e-purchasing default when goods are in e-Catalog).
- **Kepka LKPP 177/2024** — e-Katalog adjustments.
- **Catalog V6 T&C** — platform terms for e-catalog participants.

**Distinctions that must be preserved:** official catalog record vs AJM's internal catalog mirror vs product marketing information vs catalog/provider reference. `lkpp_product_url` alone is **not** proof of catalog compliance. Where authenticity cannot be deterministically verified: `HUMAN REVIEW` / `PROFESSIONAL REVIEW` remains possible.

## LKPP-01 — E-Purchasing Default Channel

| Field | Value |
|---|---|
| Rule ID | LKPP-01 |
| Rule Name | E-purchasing default channel when goods are cataloged |
| Domain | LKPP / INAPROC / E-Catalog |
| Priority | HIGH |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | Kepka LKPP 122/2022 |
| Article | e-Purchasing rules |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Preconditions | Goods are in e-Catalog |
| Exceptions | Statutory exceptions + PPK assessment |
| Required Behavior | Platform must **warn** that e-purchasing is the default channel; must not auto-select |
| Forbidden Behavior | Auto-selecting e-purchasing without PPK assessment |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (PPK assessment nuance) |
| Enforcement | WARN |
| Module | 2 (Orders) |
| Testing | PROPOSED — feature test: cataloged product triggers e-purchasing default warning |
| Dependencies | depends_on: LKPP-05 (catalog status); affects: GOV-03 |

## LKPP-02 — Category Gap Matrix

| Field | Value |
|---|---|
| Rule ID | LKPP-02 |
| Rule Name | Catalog category coverage gap matrix |
| Domain | LKPP / INAPROC / E-Catalog |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Kepka LKPP 122/2022, Kepka LKPP 177/2024 |
| Article | e-Katalog rules |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Track which product categories are cataloged vs not (category gap matrix, not % coverage) |
| Forbidden Behavior | Present a % coverage number as if it were a compliance metric |
| Automation Class | DETERMINISTIC (category mapping) |
| Enforcement | LOG_ONLY |
| Module | 3 (Products) |
| Testing | PROPOSED — integration test: category coverage report |
| Dependencies | affects: LKPP-01, TKDN-04 |

## LKPP-03 — INAPROC Channel Awareness

| Field | Value |
|---|---|
| Rule ID | LKPP-03 |
| Rule Name | INAPROC channel awareness (procurement method per KPB) |
| Domain | LKPP / INAPROC / E-Catalog |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 17/2023 (INAPROC basis) |
| Article | Percepatan Transformasi Digital Pengadaan |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (basis = Perpres 17/2023, NOT Kepka LKPP 21/2018) |
| Required Behavior | INAPROC channel awareness: identify procurement method per KPB |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW (method selection) |
| Module | 2 (Orders) |
| Testing | PROPOSED — feature test: INAPROC method mapping |
| Dependencies | affects: GOV-03 |

## LKPP-04 — Catalog T&C Awareness

| Field | Value |
|---|---|
| Rule ID | LKPP-04 |
| Rule Name | Catalog T&C awareness for participating suppliers |
| Domain | LKPP / INAPROC / E-Catalog |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Catalog V6 T&C |
| Article | Platform terms |
| Official Source | catalog.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Supplier acknowledgment/checklist of Catalog V6 T&C |
| Automation Class | DETERMINISTIC (acknowledgment capture) |
| Enforcement | BLOCK (until acknowledged) |
| Module | 0 (Config) / 3 (Products) |
| Testing | PROPOSED — feature test: acknowledgment required |
| Dependencies | affects: B2B-01, B2B-05 |

## LKPP-05 — E-Catalog Listing Status Tracking

| Field | Value |
|---|---|
| Rule ID | LKPP-05 |
| Rule Name | E-catalog listing status tracking for own products |
| Domain | LKPP / INAPROC / E-Catalog |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Catalog V6 T&C |
| Article | Platform terms |
| Official Source | catalog.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Track own-product e-catalog listing status; `lkpp_product_url` alone is NOT proof of compliance |
| Forbidden Behavior | Treat an internal URL reference as an official catalog record |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (authenticity) |
| Enforcement | WARN |
| Module | 3 (Products) |
| Testing | PROPOSED — integration test: catalog status tracking |
| Dependencies | depends_on: LKPP-04; affects: LKPP-01, PROH-02 |

## LKPP-06 — Do Not Present Non-Catalog Offers as E-Catalog Offers

| Field | Value |
|---|---|
| Rule ID | LKPP-06 |
| Rule Name | Integrity control: no non-catalog offer presented as e-catalog |
| Domain | LKPP / INAPROC / E-Catalog |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Kepka LKPP 122/2022 |
| Article | e-Purchasing rules |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Block/flag offers claiming e-catalog status without verified catalog record |
| Forbidden Behavior | Presenting non-catalog offers as e-catalog offers |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | BLOCK (unverified catalog claim) / REQUIRE_REVIEW |
| Module | 2 (Orders) |
| Testing | PROPOSED — feature test: non-catalog offer flagged |
| Dependencies | depends_on: LKPP-05; affects: PROH-02 |

---

# 6. TKDN / SNI RULES

Domain: TKDN / SNI / Product Compliance (Cluster B). Rule IDs: `TKDN-*`.

**Regulatory frame (audit §7.1):** TKDN and SNI requirements attach to **products/services offered to government**, per procurement regulations and LKPP catalog rules. Evidence-based, not percentage-based in the platform.

**Distinctions to preserve:** TKDN claim vs TKDN evidence; SNI claim vs SNI evidence; manufacturer data; country of origin; product marketing information.

## TKDN-01 — TKDN Evidence Capture

| Field | Value |
|---|---|
| Rule ID | TKDN-01 |
| Rule Name | Capture & store TKDN certificate/evidence per product |
| Domain | TKDN / SNI |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED (only for cataloged/offered products) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018 jo 46/2025; Kepka LKPP 122/2022 |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional) |
| Required Behavior | Capture TKDN certificate/evidence attributes (certificate, number, expiry) — evidence attribute, NOT auto-% |
| Forbidden Behavior | Claiming TKDN without recorded evidence; inferring % automatically |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (authenticity) |
| Enforcement | REQUIRE_REVIEW |
| Module | 3 (Products) |
| System Impact | Product certifications entity; supplier/product compliance records (§24.2-B8) |
| Testing | PROPOSED — feature test: TKDN evidence required for certified claims |
| Dependencies | affects: TKDN-03, TKDN-04, PROH-03 |

## TKDN-02 — SNI Evidence Capture

| Field | Value |
|---|---|
| Rule ID | TKDN-02 |
| Rule Name | Capture & store SNI certificate/evidence per product |
| Domain | TKDN / SNI |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018 jo 46/2025; Kepka LKPP 122/2022 |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional) |
| Required Behavior | Capture SNI certificate/evidence per product |
| Forbidden Behavior | SNI claim without evidence |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (authenticity) |
| Enforcement | REQUIRE_REVIEW |
| Module | 3 (Products) |
| Testing | PROPOSED — feature test: SNI evidence attributes |
| Dependencies | affects: TKDN-03, PROH-03 |

## TKDN-03 — Evidence Currency / Expiry Validation

| Field | Value |
|---|---|
| Rule ID | TKDN-03 |
| Rule Name | Validate evidence currency/expiry |
| Domain | TKDN / SNI |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | General |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (as recommended control) |
| Required Behavior | Expiry alerts on TKDN/SNI certificates |
| Automation Class | DETERMINISTIC (expiry) / HUMAN (authenticity) |
| Enforcement | WARN |
| Module | 3 (Products) |
| Testing | PROPOSED — unit test: expiry computation |
| Dependencies | depends_on: TKDN-01, TKDN-02 |

## TKDN-04 — Non-Certified Product Warning (Government)

| Field | Value |
|---|---|
| Rule ID | TKDN-04 |
| Rule Name | Warning when non-certified product offered to government |
| Domain | TKDN / SNI |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Kepka LKPP 122/2022 |
| Article | e-Purchasing rules |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (as recommended control) |
| Required Behavior | Procurement-channel warning when non-certified product offered to government |
| Automation Class | DETERMINISTIC (catalog-status dependent) + HUMAN REVIEW |
| Enforcement | WARN |
| Module | 3 (Products) |
| Testing | PROPOSED — integration test: warning on non-certified offer |
| Dependencies | depends_on: TKDN-01, TKDN-02; affects: GOV-03 |

## TKDN-05 — No Automatic % TKDN Inference

| Field | Value |
|---|---|
| Rule ID | TKDN-05 |
| Rule Name | TKDN % only from evidence — no auto-inference (system safety / data-integrity) |
| Domain | TKDN / SNI |
| Priority | LOW |
| Reg. Applicability | NOT APPLICABLE (system safety / data-integrity rule — NOT a statutory applicability rule) |
| System Readiness | MISSING |
| Real-World Compliance | NOT APPLICABLE |
| Regulation | — |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (data-integrity safeguard: prohibits inferring an authoritative % without evidence) |
| Required Behavior | No auto-% TKDN computation; % TKDN only from certificate/evidence data (data-integrity safeguard — do not infer an authoritative TKDN percentage without evidence) |
| Forbidden Behavior | Presenting platform-computed % TKDN as authoritative |
| Automation Class | DETERMINISTIC (display only, from certificate data) |
| Enforcement | LOG_ONLY |
| Module | 3 (Products) |
| Testing | PROPOSED — regression test: no auto-% computation |
| Dependencies | depends_on: TKDN-01 |

---

# 7. ELECTRONIC TRANSACTION / CONTRACT RULES

Domain: B2B / Electronic Transactions / Contracts (Cluster A/C). Rule IDs: `B2B-*`, `ETS-*`.

**Legal basis (audit §8.1):** B2B contracts formed via the platform are **electronic transactions** under UU ITE 11/2008 jo 1/2024. Electronic records and electronic signatures are admissible evidence (UU ITE Ps 5–6, 11–12; PP 71/2019 Ps 47–49).

**Signature nuance (§8.3):** The platform may use non-certified electronic signatures. Their validity is **conditional** on the six criteria in UU ITE Ps 11 — not automatically invalid. **Do not implement a boolean "certified vs not" rule.**

## B2B-01 — Consent / Acceptance Capture per Transaction

| Field | Value |
|---|---|
| Rule ID | B2B-01 |
| Rule Name | Capture consent/acceptance events for each transaction |
| Domain | B2B / Electronic Transactions |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL (audit log timestamps exist; strengthen) |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024; PP 71/2019 |
| Article | Ps 5–6, 18–19; Ps 47 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Record consent/acceptance event with timestamp and identity for each transaction |
| Automation Class | DETERMINISTIC (event capture) |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| Testing | PROPOSED — feature test: consent event recorded |
| Dependencies | affects: ETS-03, DOC-01, PSE-AUDIT-001 |

## B2B-02 — Electronic Record Integrity & Retrievability

| Field | Value |
|---|---|
| Rule ID | B2B-02 |
| Rule Name | Store electronic records with integrity & retrievability |
| Domain | B2B / Electronic Transactions |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL (Module 8 audit log exists) |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024; PP 71/2019 |
| Article | Ps 5; Ps 22(1) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Integrity-protected, retrievable electronic records |
| Automation Class | DETERMINISTIC (integrity hashing) |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| Testing | PROPOSED — integration test: record integrity check |
| Dependencies | affects: PSE-AUDIT-001, DOC-02 |

## B2B-03 — Non-Certified Electronic Signature Validity

| Field | Value |
|---|---|
| Rule ID | B2B-03 |
| Rule Name | Non-certified electronic signature validity (six-criteria rule) |
| Domain | B2B / Electronic Transactions |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024; PP 71/2019 |
| Article | Ps 11; Ps 52–53 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (as cautious, non-boolean rule) |
| Required Behavior | Evaluate signature validity against the six UU ITE Ps 11 criteria; cautious wording — NOT a boolean certified/not rule |
| Forbidden Behavior | Automatically invalidating non-certified signatures; boolean classification |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW (legal assessment on contested cases) |
| Module | 8 (Audit Logs) / 7 (Documents) |
| Testing | PROPOSED — integration test: six-criteria checklist present |
| Dependencies | affects: B2B-04, DOC-02 |

## B2B-04 — Certified TTE Support

| Field | Value |
|---|---|
| Rule ID | B2B-04 |
| Rule Name | Support certified TTE when requested by counterparties |
| Domain | B2B / Electronic Transactions |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024; PP 71/2019 |
| Article | Ps 11(2); Ps 53 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional) |
| Required Behavior | PSrE-certified signing module support when counterparty requires certified TTE |
| Automation Class | DETERMINISTIC (when configured) + HUMAN REVIEW (selection) |
| Enforcement | REQUIRE_REVIEW |
| Module | 7 (Documents) |
| System Impact | Certified TTE signing module; certificate registry (distinct from PSE-CERT-001) |
| Testing | PROPOSED — feature test: certified TTE signing path |
| Dependencies | depends_on: (conceptually distinct from) PSE-CERT-001; affects: B2B-03 |

## B2B-05 — Binding Platform Terms (ToS)

| Field | Value |
|---|---|
| Rule ID | B2B-05 |
| Rule Name | Terms & conditions of platform use binding (electronic-contract binding; ToS gate = AJM governance) |
| Domain | B2B / Electronic Transactions |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED (legal electronic-contract binding, Ps 18) — ToS acceptance gate is AJM governance policy, NOT a statutory blocker |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024 |
| Article | Ps 18 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | (a) Legal: electronic transactions conducted via the platform bind the parties (UU ITE Ps 18). (b) AJM governance: present and capture ToS acceptance before platform use (system capability preserved) |
| Automation Class | DETERMINISTIC (acceptance capture) |
| Enforcement | BLOCK (until ToS accepted) — AJM policy gate, not a statutory requirement |
| Module | 0 (Config) |
| Testing | PROPOSED — feature test: ToS acceptance required |
| Dependencies | affects: GOV-05, B2B-01 |

## ETS-01 — Timestamp Integrity

| Field | Value |
|---|---|
| Rule ID | ETS-01 |
| Rule Name | Record timestamp integrity |
| Domain | Electronic Transaction / Contract / Signature |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 47, 49 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Preserve timestamp integrity on transaction records (engineering integrity control supporting statutory record/evidence admissibility, PP 71/2019 Ps 47–49). Tamper-evidence is an engineering control, not itself a statutory mandate for a specific mechanism |
| Automation Class | DETERMINISTIC |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| Testing | PROPOSED — unit test: timestamp integrity |
| Dependencies | affects: B2B-01, PSE-AUDIT-001 |

## ETS-02 — Electronic Records Admissible & Preserved

| Field | Value |
|---|---|
| Rule ID | ETS-02 |
| Rule Name | Electronic records admissible & preserved |
| Domain | Electronic Transaction / Contract / Signature |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024; PP 71/2019 |
| Article | Ps 5; Ps 22(1) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Preserve admissible electronic records (audit trail) |
| Automation Class | DETERMINISTIC (logging) |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| Testing | PROPOSED — compliance test: record preservation |
| Dependencies | affects: DOC-01, DOC-02, PSE-AUDIT-001 |

## ETS-03 — Consent Capture per Electronic Transaction

| Field | Value |
|---|---|
| Rule ID | ETS-03 |
| Rule Name | Consent capture per electronic transaction |
| Domain | Electronic Transaction / Contract / Signature |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024 |
| Article | Ps 18–19 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Capture consent per electronic transaction |
| Automation Class | DETERMINISTIC (event capture) |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| Testing | PROPOSED — feature test: consent per transaction |
| Dependencies | affects: B2B-01, PDP-PROC-001 (interplay) |

---

# 8. PSE RULES

Domain: PSE (Cluster C/E). Rule IDs: `PSE-*`, plus `INC-PSE-*` (detailed in §15).

**Classification (audit §10.1):** AJM is a **PSE lingkup privat** (PP 71/2019 Ps 2(2)) conducting **electronic transactions**. Applies: registration, electronic certificate, audit trail, reliability/security, incident reporting, sanctions exposure.

**Distinct concepts that must NOT be conflated (audit §10.3):**

| Concept | Basis | Distinct from PSE-CERT-001 |
|---|---|---|
| User TTE (individual signature) | UU ITE Ps 11; PP 71/2019 Ps 52–53 | Yes — different holder/object |
| Certified TTE | PP 71/2019 Ps 53 | Yes — TTE + certification |
| BSrE (BSSN Balai Sertifikasi Elektronik) | BSSN regs | Yes — public-sector CA, not AJM's issuer |
| PSrE (Penyelenggara Sertifikasi Elektronik) | PP 71/2019; PM 11/2022 | PSrE = the CA; AJM is a **holder**, not a PSrE |
| Sertifikat Keandalan / LSK | PP 71/2019 Ps 42–43 | Yes — different document (reliability trust mark), different issuer (LSK) |
| LS PSrE | PM 11/2022 | Yes — assesses PSrE readiness; NOT certificate issuer, NOT LSK |

PSE-level certificates and individual/document-signing certificates are represented separately.

## PSE-REG-001 — PSE Registration Before Use

| Field | Value |
|---|---|
| Rule ID | PSE-REG-001 |
| Rule Name | PSE registration before system used by users |
| Domain | PSE |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 6(1) |
| Official Source | jdih.komdigi.go.id (`view/id/695`) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Record PSE registration (registration number, date, evidence) |
| Forbidden Behavior | Claim PSE-registered status without registration record |
| Automation Class | GOVERNANCE / MONITORING (registry) |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config/Platform) |
| System Impact | `pse_registration` entity; certificate registry |
| Testing | PROPOSED — feature test: registration record present |
| Dependencies | affects: PSE-REG-003, PSE-CERT-001 |

## PSE-REG-002 — PSE Privat Registration Interface

| Field | Value |
|---|---|
| Rule ID | PSE-REG-002 |
| Rule Name | PSE privat registration interface (PMSE/electronic services) |
| Domain | PSE |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Permenkominfo 5/2020 jo 10/2021 |
| Article | Ps 4–6 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Support PSE privat registration interface/record |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config/Platform) |
| Testing | PROPOSED — feature test: interface record |
| Dependencies | depends_on: PSE-REG-001 |

## PSE-REG-003 — Registration Data Accuracy & Maintenance

| Field | Value |
|---|---|
| Rule ID | PSE-REG-003 |
| Rule Name | Registration data accuracy & maintenance |
| Domain | PSE |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Permenkominfo 5/2020 jo 10/2021 |
| Article | Ps 7 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Maintain and update registration data; re-verify per §20 |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config/Platform) |
| Testing | PROPOSED — feature test: data maintenance |
| Dependencies | depends_on: PSE-REG-001 |

## PSE-GOV-001 — System Reliability & Security Obligations

| Field | Value |
|---|---|
| Rule ID | PSE-GOV-001 |
| Rule Name | System reliability & security obligations |
| Domain | PSE |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 11, 12, 13, 14 |
| Official Source | jdih.komdigi.go.id (`view/id/695`) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Maintain reliability/security controls per PSE obligations |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 1–8 (platform ops) |
| Testing | PROPOSED — compliance test: reliability/security evidence |
| Dependencies | affects: SEC-01..07, PSE-SEC-001 |

## PSE-GOV-002 — Continuity & Disaster Recovery

| Field | Value |
|---|---|
| Rule ID | PSE-GOV-002 |
| Rule Name | Continuity & disaster recovery |
| Domain | PSE |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | **Ps 19** (tata kelola & keberlangsungan) |
| Official Source | jdih.komdigi.go.id (`view/id/695`) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED — **Ps 21(1) is overseas processing/storage, NOT a DR mandate.** DR is engineering best practice layered on Ps 19. |
| Required Behavior | Continuity & tata kelola keberlangsungan controls |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 1–8 (platform ops) |
| Testing | PROPOSED — compliance test: continuity evidence |
| Dependencies | affects: SEC-05 |

## PSE-GOV-003 — User Assistance / Information

| Field | Value |
|---|---|
| Rule ID | PSE-GOV-003 |
| Rule Name | User assistance/information |
| Domain | PSE |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 13, 14 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Provide user assistance/information channels |
| Automation Class | DETERMINISTIC (information display) |
| Enforcement | LOG_ONLY |
| Module | 1–8 (platform ops) |
| Testing | PROPOSED — feature test: user assistance present |
| Dependencies | affects: PSE-GOV-001 |

## PSE-DATA-001 — Data Protection in PSE

| Field | Value |
|---|---|
| Rule ID | PSE-DATA-001 |
| Rule Name | Data protection in PSE (PDP principles) |
| Domain | PSE / PDP |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 16 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Apply PDP principles (purpose limitation, data protection by design/default) |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) / cross-cutting |
| Testing | PROPOSED — compliance test: PDP principles evidence |
| Dependencies | affects: SEC-01, PROH-04 |

## PSE-DATA-002 — Data Localization / Overseas Transfer

| Field | Value |
|---|---|
| Rule ID | PSE-DATA-002 |
| Rule Name | Data localization — overseas transfer permitted with safeguards |
| Domain | PSE / PDP |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | UNVERIFIED |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 21(1) |
| Official Source | jdih.komdigi.go.id (`view/id/695`) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED — **NOT a blanket Indonesia-only rule** |
| Required Behavior | Permit overseas transfer with safeguards; not a blanket localization mandate |
| Forbidden Behavior | Blanket "Indonesia-only" enforcement |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (safeguard assessment) |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config) / 4 (Companies) |
| Testing | PROPOSED — feature test: overseas-transfer safeguard flag |
| Dependencies | — |

## PSE-DATA-003 — Strategic-Data Classification/Localization

| Field | Value |
|---|---|
| Rule ID | PSE-DATA-003 |
| Rule Name | Strategic-data classification/localization |
| Domain | PSE |
| Priority | MEDIUM |
| Reg. Applicability | UNCERTAIN / CONDITIONAL / LEGAL REVIEW |
| System Readiness | UNVERIFIED |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 14 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | UNCERTAIN — not all personal or government data is automatically strategic; classification is fact-specific |
| Required Behavior | Strategic-data classification/localization only; personal-data protection principles = PSE-DATA-001 |
| Forbidden Behavior | Auto-classifying data as strategic without fact-specific review; NO automatic Indonesia-only storage/blocking |
| Automation Class | PROFESSIONAL LEGAL/TAX REVIEW (no automatic decision) |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config) |
| Testing | PROPOSED — compliance test: classification procedure |
| Dependencies | affects: PSE-DATA-002 |

## PSE-AUDIT-001 — Audit Trail of System Operations

| Field | Value |
|---|---|
| Rule ID | PSE-AUDIT-001 |
| Rule Name | Audit trail (log) of system operations |
| Domain | PSE |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL (Module 8 exists — must be mapped to PSE obligation) |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | **Ps 22(1)** |
| Official Source | jdih.komdigi.go.id (`view/id/695`) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Map Module 8 audit log to PSE audit-trail obligation (Ps 22(1)) |
| Automation Class | DETERMINISTIC (logging) |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| System Impact | Audit-trail adapter; `audit_trail_mapping` entity |
| Testing | PROPOSED — compliance test: Module 8 → Ps 22(1) mapping |
| Dependencies | affects: B2B-*, ETS-*, DOC-*, ROLE-* |

## PSE-SEC-001 — Security Assurance / Reliability (Uji Kelayakan)

| Field | Value |
|---|---|
| Rule ID | PSE-SEC-001 |
| Rule Name | Security assurance / reliability (incl. uji kelayakan) |
| Domain | PSE / Security |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 11, 12 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Security assurance / reliability (uji kelayakan) evidence |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 1–8 (platform ops) |
| Testing | PROPOSED — compliance test: uji kelayakan evidence |
| Dependencies | affects: SEC-01..07 |

## PSE-CERT-001 — Sertifikat Elektronik (PSE Electronic Certificate)

| Field | Value |
|---|---|
| Rule ID | PSE-CERT-001 |
| Rule Name | PSE must hold Sertifikat Elektronik issued by PSrE Indonesia |
| Domain | PSE |
| Priority | HIGH |
| Reg. Applicability | **REQUIRED** |
| System Readiness | **MISSING** |
| Real-World Compliance | **UNVERIFIED** |
| Regulation | PP 71/2019; Permenkominfo 11/2022 |
| Article | PP 71/2019 **Ps 51(1),(3)**; Permenkominfo 11/2022 **Ps 24(2), Ps 25** |
| Exact text (Ps 51(1)) | "Penyelenggara Sistem Elektronik sebagaimana dimaksud dalam Pasal 2 ayat (2) **wajib memiliki Sertifikat Elektronik**." |
| Exact text (Ps 51(3)) | "Untuk memiliki Sertifikat Elektronik, Penyelenggara Sistem Elektronik dan Pengguna Sistem Elektronik **harus mengajukan permohonan kepada Penyelenggara Sertifikasi Elektronik Indonesia**." |
| Implementing (PM 11/2022 Ps 24(2)) | "Penyelenggara Sistem Elektronik **wajib memiliki Sertifikat Elektronik yang diterbitkan oleh PSrE Indonesia**." |
| Lifecycle | Ps 25–26, 29(2): issuance, verification, renewal (perpanjangan), blocking (pemblokiran), revocation (pencabutan) |
| Issuer | PSrE Indonesia (badan hukum Indonesia, recognized by Menteri, berinduk kepada PSrE Induk (Komdigi)); AJM uses PSrE non-Instansi |
| Official Source | jdih.komdigi.go.id (`view/id/695`; `view/id/833`) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (legal obligation); IMPLEMENTATION DETAILS → `REQUIRES FURTHER VERIFICATION` |
| Required Behavior | Certificate registry: Sertifikat Elektronik, PSrE info, expiry; distinct from user TTE |
| Forbidden Behavior | Conflating PSE certificate with user TTE / BSrE / Sertifikat Keandalan |
| Automation Class | DETERMINISTIC (expiry tracking) + GOVERNANCE |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config/Platform) |
| System Impact | `pse_certificates` entity; certificate registry |
| Testing | PROPOSED — feature test: certificate record + expiry flag |
| Dependencies | depends_on: PSE-REG-001; affects: B2B-04 (conceptually distinct) |

## PSE-SANC-001 — Sanctions Exposure Awareness

| Field | Value |
|---|---|
| Rule ID | PSE-SANC-001 |
| Rule Name | Sanctions exposure for PSE violations |
| Domain | PSE |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED (awareness) |
| System Readiness | NOT APPLICABLE |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 100 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Maintain awareness of sanctions exposure (governance record) |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | LOG_ONLY |
| Module | 0 (Config) |
| Testing | PROPOSED — governance check |
| Dependencies | affects: PSE-REG-001, PSE-CERT-001 |

---

# 9. PMSE RULES

Domain: PMSE (Cluster C). Rule IDs: `PMSE-*`.

**Classification (audit §11.1):** Under Permendag 31/2023, AJM is classified as **Pedagang (supplier/seller)**, not "Retail Online" — **conditional** on exact product/service and channel definitions. Must be validated by professional review against current definitions.

**Uncertainties preserved:** exact AJM classification under Permendag 31/2023 (Pedagang vs Retail Online vs platform) → `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW`. 99.9% uptime, Indonesia-only hosting, and annual-report obligations are **NOT universal** and were removed as universal rules.

## PMSE-01 — Supplier Identity & Business License Visibility

| Field | Value |
|---|---|
| Rule ID | PMSE-01 |
| Rule Name | Supplier identity & business license visibility |
| Domain | PMSE |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Permendag 31/2023 |
| Article | PMSE licensing provisions |
| Official Source | jdih.kemendag.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional) |
| Required Behavior | Display supplier identity & business license info |
| Automation Class | DETERMINISTIC (display) |
| Enforcement | WARN |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: license visibility |
| Dependencies | depends_on: GOV-02; affects: PMSE-02 |

## PMSE-02 — PMSE Business Licensing Compliance

| Field | Value |
|---|---|
| Rule ID | PMSE-02 |
| Rule Name | Compliance with PMSE business licensing (NIB-based) |
| Domain | PMSE |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Permendag 31/2023 |
| Article | PMSE licensing provisions |
| Official Source | jdih.kemendag.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional) |
| Required Behavior | NIB-based PMSE licensing evidence tracking |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: NIB-based license record |
| Dependencies | depends_on: GOV-02 |

## PMSE-03 — Transaction Data Integrity

| Field | Value |
|---|---|
| Rule ID | PMSE-03 |
| Rule Name | Transaction data integrity |
| Domain | PMSE |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 80/2019 |
| Article | PMSE provisions |
| Official Source | jdih.setkab.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Integrity-protected transaction data |
| Automation Class | DETERMINISTIC (integrity) |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| Testing | PROPOSED — integration test: transaction integrity |
| Dependencies | affects: B2B-02, DOC-02 |

## PMSE-04 — Do NOT Apply "Retail Online" Obligations Without Validation

| Field | Value |
|---|---|
| Rule ID | PMSE-04 |
| Rule Name | Do NOT apply "Retail Online" obligations without validation |
| Domain | PMSE |
| Priority | MEDIUM |
| Reg. Applicability | NOT APPLICABLE (conditional) |
| System Readiness | NOT APPLICABLE |
| Real-World Compliance | NOT APPLICABLE |
| Regulation | Permendag 31/2023 |
| Article | Retail Online provisions |
| Official Source | jdih.kemendag.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | UNCERTAIN (classification unresolved) |
| Required Behavior | Do not apply Retail Online obligations without validated classification |
| Forbidden Behavior | Applying Retail Online obligations by assumption |
| Automation Class | PROFESSIONAL LEGAL/TAX REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config) |
| Testing | PROPOSED — governance check |
| Dependencies | depends_on: PMSE-01, PMSE-02 |

---

# 10. TAX RULES

Domain: Tax / DJP (Cluster D). Rule IDs: `TAX-*`. **Extra-strict section.**

**Conceptual tax model (no hardcoded rates):** tax rules are represented conceptually using: Tax Type, Taxpayer Status, Buyer Classification, VAT Collector Status, Transaction Type, Product Classification, Base Amount, DPP Method, DPP Formula, Statutory Rate, Tax Formula, Effective Burden, Faktur Pajak Code, Withholding Rule, Effective From, Effective Until, Legal Reference. The model must support the distinction between: **statutory rate, DPP method, effective tax burden, Faktur Pajak transaction code, withholding tax, effective date**. The 11/12 factor is part of **DPP determination** (DPP Formula = Base Amount × 11/12); it is never an additional multiplier applied after DPP is calculated.

**Tax rules are versioned.** Historical transactions must preserve the tax rule snapshot used at transaction time. The `TaxRule` engine is a deterministic, versioned rule registry — rates are **never hardcoded** (`tax_rate = 11` / `= 12` are invalid representations).

**Coretax / API distinction:** "Coretax is the official tax platform" does NOT imply "AJM must implement direct Coretax API integration." Keep **legal tax obligation, official filing mechanism, optional technical integration** as separate concepts. API integration is optional unless a current legal/technical source establishes otherwise.

## TAX-PPN-01 — PPN Rate & DPP Calculation

| Field | Value |
|---|---|
| Rule ID | TAX-PPN-01 |
| Rule Name | PPN rate & DPP calculation |
| Domain | Tax / PPN |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Tax Type | PPN |
| Taxpayer Status | PKP — **UNVERIFIED/assumed** (design precondition §24.1-A1) |
| Buyer Classification | Generic classes for the TaxRule engine: regular buyer, government, BUMN, other designated collector, etc. (faktur code selection = TAX-PPN-02; B2G collector condition = TAX-PPN-04 — separate dimensions) |
| Transaction Type | Penyerahan BKP/JKP |
| Base Amount | Harga Jual |
| DPP Method | NILAI_LAIN |
| DPP Formula | Base Amount × 11/12 (the 11/12 factor determines DPP; it is NOT an additional multiplier applied after DPP is calculated) |
| Statutory Rate | 12% |
| Tax Formula | DPP × Statutory Rate |
| Effective Burden | 11% of Harga Jual for the applicable standard non-luxury case |
| Faktur Pajak Code | per hierarchy (TAX-PPN-02) |
| Regulation | PMK 11/2025 jo PMK 53/2025 (DPP nilai lain); PMK 131/2024 (PPN treatment — rate basis) |
| Article | DPP nilai lain provisions; PMK 131/2024 PPN treatment |
| Official Source | jdih.kemenkeu.go.id |
| Verification Date | 13-Aug-2026 |
| Effective From | Versioned (rate changes effective-dated) |
| Effective Until | Versioned via TaxRule engine; changes monitored (§20 watch item 3) |
| Confidence | CONFIRMED (basis); effective-rate changes → versioned rules |
| Required Behavior | DPP determination (Base Amount × 11/12) followed by a single tax application (DPP × 12%) via TaxRule engine (versioned, source-cited) — no double application of the 11/12 factor |
| Forbidden Behavior | Hardcoding tax rates; treating PPN as uniformly 12%; applying the 11/12 factor after DPP has already been calculated (double application) |
| Automation Class | DETERMINISTIC (with versioned rules); exception review; legal review before rate change |
| Enforcement | BLOCK (invalid calculation) |
| Module | 5 (Payments) |
| System Impact | TaxRule engine; `tax_rules` entity (versioned) |
| Testing | PROPOSED — unit test: DPP nilai lain calc; versioned rate snapshot test |
| Dependencies | affects: TAX-PPN-02, TAX-PPN-03, TAX-PPN-04 |

## TAX-PPN-02 — Faktur Pajak Code Selection (Hierarchy)

| Field | Value |
|---|---|
| Rule ID | TAX-PPN-02 |
| Rule Name | Faktur pajak code selection (hierarchy) |
| Domain | Tax / PPN |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Tax Type | PPN |
| Faktur Pajak Code | **Hierarchy, not equivalent picklist:** code 01 = default; code 02 = only government VAT collectors; code 03 = only designated collectors |
| Regulation | PER-11/PJ/2025 |
| Article | Lampiran D (kode & nomor seri faktur pajak) |
| Official Source | jdih.kemenkeu.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Select faktur code by hierarchy (buyer classification determines eligibility) |
| Forbidden Behavior | Arbitrary code selection; allowing code 02/03 for non-collector buyers |
| Automation Class | DETERMINISTIC (hierarchy-based) |
| Enforcement | **BLOCK** (wrong faktur code) |
| Module | 5 (Payments) |
| System Impact | TaxRule engine; `faktur_codes` entity |
| Testing | PROPOSED — unit test: code selection per buyer class |
| Dependencies | depends_on: TAX-PPN-01, TAX-PPN-04; affects: TAX-PPN-03 |

## TAX-PPN-03 — E-Faktur Issuance & Reporting

| Field | Value |
|---|---|
| Rule ID | TAX-PPN-03 |
| Rule Name | E-Faktur issuance & reporting |
| Domain | Tax / PPN |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Tax Type | PPN |
| Regulation | **PER-1/PJ/2025 (Petunjuk Teknis Pembuatan Faktur Pajak) + PER-11/PJ/2025 (SIAP reporting) + PMK 81/2024 (SIAP/Coretax umbrella)** |
| Article | PER-1/PJ/2025 Petunjuk Teknis; PER-11/PJ/2025 reporting; PMK 81/2024 SIAP |
| Official Source | jdih.kemenkeu.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED — PMK 131/2024 is PPN *treatment*, NOT E-Faktur procedure |
| Required Behavior | E-Faktur issuance & SIAP reporting flows (via TaxRule engine / filing mechanism) |
| Forbidden Behavior | Representing E-Faktur procedure as PMK 131/2024 |
| Automation Class | DETERMINISTIC (issuance logic) + HUMAN REVIEW (filing exceptions) |
| Enforcement | BLOCK (invalid issuance) / REQUIRE_REVIEW |
| Module | 5 (Payments) |
| System Impact | TaxRule engine; filing mechanism interface (optional integration) |
| Testing | PROPOSED — feature test: E-Faktur issuance flow; reporting data assembly |
| Dependencies | depends_on: TAX-PPN-02, TAX-PPN-01, TAX-PPN-05 |

## TAX-PPN-04 — B2G VAT Collection

| Field | Value |
|---|---|
| Rule ID | TAX-PPN-04 |
| Rule Name | B2G VAT collection (instansi pemerintah) |
| Domain | Tax / PPN |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Tax Type | PPN |
| VAT Collector Status | Applies where customer is a VAT-collecting government institution — **condition** (design precondition §24.1-A3) |
| Regulation | PMK 59/2022 |
| Article | Pemungutan PPN instansi pemerintah |
| Official Source | jdih.kemenkeu.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional on collector status) |
| Required Behavior | B2G VAT collection where counterparty is a VAT-collecting government institution |
| Forbidden Behavior | Assuming B2G VAT collection for all government customers without collector-status evidence |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (collector-status verification) |
| Enforcement | REQUIRE_REVIEW (collector status) / BLOCK (incorrect code) |
| Module | 5 (Payments) |
| System Impact | TaxRule engine; counterparty classification data |
| Testing | PROPOSED — feature test: B2G collection path |
| Dependencies | depends_on: TAX-PPN-05; affects: TAX-PPN-02 |

## TAX-PPN-05 — NPWP/NIK Identity on Counterparties

| Field | Value |
|---|---|
| Rule ID | TAX-PPN-05 |
| Rule Name | NPWP/NIK identity on counterparties |
| Domain | Tax / PPN |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Tax Type | PPN |
| Regulation | **PER-11/PJ/2025** (identitas pembeli fields) — NOT PER-1/PJ/2025 (that is faktur *pembuatan* teknis) |
| Article | SIAP reporting; buyer identity fields |
| Official Source | jdih.kemenkeu.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Capture & validate counterparty NPWP/NIK identity |
| Forbidden Behavior | Recording incomplete/invalid buyer identity on faktur |
| Automation Class | DETERMINISTIC (validation) |
| Enforcement | BLOCK (invalid identity) |
| Module | 4 (Companies) |
| System Impact | Supplier-eligibility service; counterparty identity fields |
| Testing | PROPOSED — unit test: NPWP/NIK validation |
| Dependencies | affects: TAX-PPN-03, TAX-PPN-04 |

## TAX-PPN-06 — No "SPT Tahunan PPN" Concept

| Field | Value |
|---|---|
| Rule ID | TAX-PPN-06 |
| Rule Name | No "SPT Tahunan PPN" concept |
| Domain | Tax / PPN |
| Priority | LOW |
| Reg. Applicability | NOT APPLICABLE (no such filing) |
| System Readiness | NOT APPLICABLE |
| Real-World Compliance | NOT APPLICABLE |
| Regulation | — |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (as removal of an incorrect rule) |
| Required Behavior | No "SPT Tahunan PPN" filing concept |
| Forbidden Behavior | Implementing a nonexistent SPT Tahunan PPN |
| Automation Class | N/A (excluded) |
| Enforcement | N/A |
| Module | N/A |
| Testing | PROPOSED — regression: no SPT Tahunan PPN references |
| Dependencies | — |

## TAX-PPH-01 — PPh 23 Withholding Tracking

| Field | Value |
|---|---|
| Rule ID | TAX-PPH-01 |
| Rule Name | PPh 23 withholding tracking (sell & buy side separation) |
| Domain | Tax / PPh |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Tax Type | PPh 23 |
| Withholding Rule | Sell-side vs buy-side separated; exact model fact-specific |
| Regulation | UU PPh; PP implementing |
| Article | PPh 23 provisions |
| Official Source | jdih.kemenkeu.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | **REQUIRES PROFESSIONAL TAX REVIEW** for exact model |
| Required Behavior | Track PPh 23 withholding with sell/buy-side separation |
| Forbidden Behavior | Single-sided PPh 23 handling |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 5 (Payments) |
| System Impact | TaxRule engine (PPh flags) |
| Testing | PROPOSED — feature test: sell/buy-side PPh tracking |
| Dependencies | affects: TAX-PPH-03 |

## TAX-PPH-02 — PPh 21 for Employees

| Field | Value |
|---|---|
| Rule ID | TAX-PPH-02 |
| Rule Name | PPh 21 for employees |
| Domain | Tax / PPh |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Tax Type | PPh 21 |
| Regulation | UU PPh |
| Article | PPh 21 provisions |
| Official Source | jdih.kemenkeu.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (HR scope; exact model fact-specific) |
| Required Behavior | PPh 21 handling (HR scope) |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 5 (Payments) / HR |
| Testing | PROPOSED — feature test: PPh 21 handling |
| Dependencies | — |

## TAX-PPH-03 — Tax Certificate / NPWP Evidence for Counterparties

| Field | Value |
|---|---|
| Rule ID | TAX-PPH-03 |
| Rule Name | Tax certificate/NPWP evidence for counterparties |
| Domain | Tax / PPh |
| Priority | MEDIUM |
| Reg. Applicability | **UNCERTAIN / LEGAL REVIEW** |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | **UNCERTAIN** — NOT PMK 131/2024 (that is PPN treatment) |
| Article | Unresolved |
| Official Source | Unresolved — `REQUIRES PROFESSIONAL TAX REVIEW` |
| Verification Date | 13-Aug-2026 |
| Effective | In force (basis unresolved) |
| Confidence | UNCERTAIN |
| Required Behavior | Do not cite a legal basis for tax-certificate/NPWP evidence until professional tax review resolves it |
| Forbidden Behavior | Upgrading TAX-PPH-03 to REQUIRED without a documented legal basis |
| Automation Class | **PROFESSIONAL LEGAL/TAX REVIEW** (no automatic decision) |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — governance check (basis resolution) |
| Dependencies | affects: TAX-PPN-05 (conceptually adjacent, distinct) |

## TAX-CRT-01 — Coretax Integration

| Field | Value |
|---|---|
| Rule ID | TAX-CRT-01 |
| Rule Name | Coretax integration |
| Domain | Tax / Coretax |
| Priority | LOW |
| Reg. Applicability | **OPTIONAL** (business decision, not legally mandatory) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | DJP Coretax |
| Article | Platform/API |
| Official Source | pajak.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (optional) |
| Required Behavior | Coretax API integration is optional; keep legal obligation vs official filing mechanism vs optional technical integration separate |
| Forbidden Behavior | Treating Coretax API integration as mandatory without a current legal/technical source |
| Automation Class | GOVERNANCE / MONITORING (decision) |
| Enforcement | REQUIRE_REVIEW (integration decision) |
| Module | 5 (Payments) / 0 (Config) |
| Testing | PROPOSED — decision record |
| Dependencies | affects: TAX-PPN-03 (filing mechanism) |

---

# 11. PDP / PRIVACY RULES

Domain: PDP / Privacy (Cluster E). Rule IDs: `PDP-*`.

**Rights vs procedures vs obligations vs incidents must remain separate.** PDP-PROC-001 is a **procedure, not a right**. Not all rights share the same statutory deadline — only PDP-RIGHT-005 and PDP-RIGHT-007 carry statutory 3×24h operational deadlines.

## PDP-RIGHT-001 — Information (Informasi)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-001 |
| Rule Name | Data subject right: Information |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 5 |
| Operational Deadline | Reasonable time |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: information request flow |
| Dependencies | depends_on: PDP-PROC-001 |

## PDP-RIGHT-002 — Rectification (Koreksi & Pembaruan)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-002 |
| Rule Name | Data subject right: Rectification |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 6 |
| Operational Deadline | Reasonable time |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: rectification flow |
| Dependencies | depends_on: PDP-PROC-001 |

## PDP-RIGHT-003 — Access & Copy (Akses & Salinan)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-003 |
| Rule Name | Data subject right: Access & Copy |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 7 |
| Operational Deadline | Reasonable time |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: access & copy flow |
| Dependencies | depends_on: PDP-PROC-001 |

## PDP-RIGHT-004 — Erasure (Penghapusan/Pemusnahan)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-004 |
| Rule Name | Data subject right: Erasure |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 8 |
| Operational Deadline | Reasonable time |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (exceptions) |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: erasure flow |
| Dependencies | depends_on: PDP-PROC-001; affects: DOC-04 (retention interplay) |

## PDP-RIGHT-005 — Withdraw Consent (Tarik Persetujuan)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-005 |
| Rule Name | Data subject right: Withdraw consent |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 9 |
| Operational Deadline | **3×24 hours — Ps 40(2)** (statutory) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Withdrawal workflow that stops processing within 3×24h |
| Automation Class | DETERMINISTIC (withdrawal + timer) |
| Enforcement | `STOP_PROCESSING` (AJM system-response; via PDP-3X24-001) |
| Module | 4 (Companies) / 6 (Notifications) |
| Testing | PROPOSED — feature test: withdrawal + 3×24h timer |
| Dependencies | depends_on: PDP-PROC-001; operational deadline: PDP-3X24-001 |

## PDP-RIGHT-006 — Object to Automated Decision (Keberatan Otomatis)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-006 |
| Rule Name | Data subject right: Object to automated decision |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 10 |
| Operational Deadline | Reasonable time |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Right to object to automated decisions (no profiling without basis — see PROH-06) |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: objection flow |
| Dependencies | depends_on: PDP-PROC-001; affects: PROH-06 |

## PDP-RIGHT-007 — Restriction/Suspension (Penundaan/Pembatasan)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-007 |
| Rule Name | Data subject right: Restriction/Suspension |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 11 |
| Operational Deadline | **3×24 hours — Ps 41(1)** (statutory) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Restriction/suspension action within 3×24h |
| Automation Class | DETERMINISTIC (restriction + timer) |
| Enforcement | `SUSPEND_RESTRICT_PROCESSING` (AJM system-response; via PDP-3X24-002) |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: restriction + 3×24h timer |
| Dependencies | depends_on: PDP-PROC-001; operational deadline: PDP-3X24-002 |

## PDP-RIGHT-008 — Compensation (Ganti Rugi)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-008 |
| Rule Name | Data subject right: Compensation |
| Domain | PDP / Privacy |
| Priority | LOW |
| Reg. Applicability | REQUIRED |
| System Readiness | NOT APPLICABLE |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 12 |
| Operational Deadline | Civil procedure |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | GOVERNANCE / MONITORING (civil procedure) — NOT operational fulfillment; contrast operational rights such as portability (PDP-RIGHT-009) |
| Enforcement | LOG_ONLY |
| Module | 0 (Config) |
| Testing | PROPOSED — governance record |
| Dependencies | depends_on: PDP-PROC-001 |

## PDP-RIGHT-009 — Portability (Portabilitas)

| Field | Value |
|---|---|
| Rule ID | PDP-RIGHT-009 |
| Rule Name | Data subject right: Portability |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 13 |
| Operational Deadline | Reasonable time |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Portable data export |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (operational fulfillment) — distinct from compensation/civil procedure (PDP-RIGHT-008) |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: portability export |
| Dependencies | depends_on: PDP-PROC-001 |

## PDP-PROC-001 — Data Subject Request Procedure

| Field | Value |
|---|---|
| Rule ID | PDP-PROC-001 |
| Rule Name | Data subject request procedure (permohonan tercatat, elektronik/nonelektronik) |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | **Ps 14** |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | **Procedure** for receiving and processing data-subject requests (NOT a data-subject right) |
| Forbidden Behavior | Treating PDP-PROC-001 as a right; assuming all rights have the same deadline |
| Automation Class | DETERMINISTIC (workflow) + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| System Impact | PDP service; `data_subject_requests` entity |
| Testing | PROPOSED — feature test: request intake workflow |
| Dependencies | affects: PDP-RIGHT-001..009 |

## PDP-BREACH-001 — Personal Data Breach Notification (Obligation)

| Field | Value |
|---|---|
| Rule ID | PDP-BREACH-001 |
| Rule Name | Personal data breach notification (obligation) |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | **Ps 46(1)** |
| Operational Deadline | **3×24h — PDP-3X24-003** |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Breach notification obligation (recipient, evidence, escalation path distinct from PSE disruption) |
| Automation Class | DETERMINISTIC (notification workflow) |
| Enforcement | REQUIRE_REVIEW; deadline via PDP-3X24-003 → `ESCALATION_VIOLATION_AUDIT` (not a processing BLOCK) |
| Module | 6 (Notifications) |
| Testing | PROPOSED — feature test: breach notification flow |
| Dependencies | depends_on: INC-PDP-001 (classification); deadline: PDP-3X24-003 |

**LEGAL REQUIREMENT vs AJM SYSTEM ENFORCEMENT RESPONSE (PDP 3×24h timers):**

UU 27/2022 (Ps 40(2), Ps 41(1), Ps 46(1)) provides the **legal obligation and the statutory deadline**. The statute does **not** use the software enum names below; they are **AJM's system-response semantics** — how the platform reacts once the applicable statutory deadline/event condition is met:

| Rule ID | Legal requirement (statute) | AJM system enforcement response | Meaning |
|---|---|---|---|
| PDP-3X24-001 | Consent withdrawal — stop processing within 3×24h (Ps 40(2)) | `STOP_PROCESSING` | Stop/block relevant processing after the applicable statutory deadline/event condition |
| PDP-3X24-002 | Restriction/suspension of processing within 3×24h (Ps 41(1)) | `SUSPEND_RESTRICT_PROCESSING` | Suspend/restrict the relevant processing |
| PDP-3X24-003 | Personal data breach notification within 3×24h (Ps 46(1)) | `ESCALATION_VIOLATION_AUDIT` | Escalate the breach case, record violation/audit state, and execute the required notification workflow — **NOT** a generic processing BLOCK |

## PDP-3X24-001 — Consent Withdrawal Timer

| Field | Value |
|---|---|
| Rule ID | PDP-3X24-001 |
| Rule Name | Consent withdrawal → stop processing within 3×24h |
| Domain | PDP / Privacy (timers) |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | **Ps 40(2)** |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Deterministic 3×24h timer; stop processing by deadline |
| Automation Class | **DETERMINISTIC** (timer) |
| Enforcement | `STOP_PROCESSING` (AJM system-response; legal obligation per Ps 40(2) — statute does not use this enum term) |
| Module | 6 (Notifications) / 4 (Companies) |
| Testing | PROPOSED — unit test: 3×24h timer computation |
| Dependencies | operational deadline for PDP-RIGHT-005 |

## PDP-3X24-002 — Restriction/Suspension Timer

| Field | Value |
|---|---|
| Rule ID | PDP-3X24-002 |
| Rule Name | Restriction/suspension action within 3×24h |
| Domain | PDP / Privacy (timers) |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | **Ps 41(1)** |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Deterministic 3×24h timer for restriction/suspension |
| Automation Class | **DETERMINISTIC** (timer) |
| Enforcement | `SUSPEND_RESTRICT_PROCESSING` (AJM system-response; legal obligation per Ps 41(1) — statute does not use this enum term) |
| Module | 6 (Notifications) / 4 (Companies) |
| Testing | PROPOSED — unit test: 3×24h timer computation |
| Dependencies | operational deadline for PDP-RIGHT-007 |

## PDP-3X24-003 — Breach Notification Timer

| Field | Value |
|---|---|
| Rule ID | PDP-3X24-003 |
| Rule Name | Breach notification within 3×24h |
| Domain | PDP / Privacy (timers) |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | **Ps 46(1)** |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED — same legal basis as PDP-BREACH-001 (obligation vs deadline views) |
| Required Behavior | Deterministic 3×24h timer for breach notification |
| Automation Class | **DETERMINISTIC** (timer) |
| Enforcement | `ESCALATION_VIOLATION_AUDIT` (AJM system-response; legal obligation per Ps 46(1) — statute does not use this enum term; NOT a generic processing BLOCK) |
| Module | 6 (Notifications) |
| Testing | PROPOSED — unit test: 3×24h timer computation |
| Dependencies | deadline for PDP-BREACH-001 |

## PDP-DPO-001 — DPO / PDP Function Officer Appointment

| Field | Value |
|---|---|
| Rule ID | PDP-DPO-001 |
| Rule Name | DPO / PDP function officer appointment |
| Domain | PDP / Privacy |
| Priority | MEDIUM |
| Reg. Applicability | **CONDITIONALLY REQUIRED** (trigger depends on processing scale/type) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | **Ps 53–54** (NOT Ps 25) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional) |
| Required Behavior | DPO appointment + assignment record, conditional on processing scale/type |
| Forbidden Behavior | Assuming DPO always applies (legal uncertainty must not become deterministic code) |
| Automation Class | **PROFESSIONAL LEGAL/TAX REVIEW** (applicability) + GOVERNANCE (record) |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config) |
| System Impact | `dp_roles` entity; DPO role (ROLE-02) |
| Testing | PROPOSED — feature test: DPO trigger assessment |
| Dependencies | affects: ROLE-02 |

## PDP-DPO-002 — Processor Obligations & PDP Controller Duties

| Field | Value |
|---|---|
| Rule ID | PDP-DPO-002 |
| Rule Name | Processor obligations & PDP controller duties |
| Domain | PDP / Privacy |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 26, 27 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Controller/processor duty records and agreements |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: processor agreement record |
| Dependencies | affects: SEC-06 |

---

# 12. SECURITY RULES

Domain: Security Governance (Cluster E). Rule IDs: `SEC-*`.

## SEC-01 — Data Protection by Design & Default

| Field | Value |
|---|---|
| Rule ID | SEC-01 |
| Rule Name | Data protection by design & default |
| Domain | Security |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 16 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 1–8 (cross) |
| Testing | PROPOSED — compliance test: design/default controls |
| Dependencies | affects: PSE-DATA-001, PROH-04 |

## SEC-02 — Security Incident Response Plan

| Field | Value |
|---|---|
| Rule ID | SEC-02 |
| Rule Name | Security incident response plan |
| Domain | Security |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022; PP 71/2019 |
| Article | Ps 46; Ps 24(3) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Incident response plan (distinct from PSE/PDP incident classes, see §15) |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 1–8 (cross) |
| System Impact | Breach/incident service |
| Testing | PROPOSED — compliance test: incident response plan |
| Dependencies | affects: INC-PSE-001, INC-PDP-001 |

## SEC-03 — Access Control & Least Privilege

| Field | Value |
|---|---|
| Rule ID | SEC-03 |
| Rule Name | Access control & least privilege |
| Domain | Security |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 11 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC |
| Enforcement | BLOCK (unauthorized access) |
| Module | 1 (Auth) |
| Testing | PROPOSED — feature test: least-privilege access |
| Dependencies | affects: ROLE-* |

## SEC-04 — Encryption for Sensitive Data

| Field | Value |
|---|---|
| Rule ID | SEC-04 |
| Rule Name | Encryption for sensitive data |
| Domain | Security |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019; PDP best practice |
| Article | Ps 14 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC |
| Enforcement | BLOCK (unencrypted sensitive data) |
| Module | 1–8 (cross) |
| Testing | PROPOSED — integration test: encryption at rest/in transit |
| Dependencies | affects: PSE-DATA-001 |

## SEC-05 — Availability/Continuity

| Field | Value |
|---|---|
| Rule ID | SEC-05 |
| Rule Name | Availability/continuity |
| Domain | Security |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 19 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (Ps 19 continuity basis) |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 1–8 (platform ops) |
| Testing | PROPOSED — compliance test: availability evidence |
| Dependencies | depends_on: PSE-GOV-002 |

## SEC-06 — Third-Party Processor Agreements

| Field | Value |
|---|---|
| Rule ID | SEC-06 |
| Rule Name | Third-party processor agreements (PDP) |
| Domain | Security |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 26 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: processor agreement tracking |
| Dependencies | affects: PDP-DPO-002 |

## SEC-07 — Vendor/Supplier Data Minimization

| Field | Value |
|---|---|
| Rule ID | SEC-07 |
| Rule Name | Vendor/supplier data minimization |
| Domain | Security |
| Priority | MEDIUM |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 19 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Minimize vendor/supplier data to purpose |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — compliance test: minimization controls |
| Dependencies | affects: PROH-04 |

---

# 13. DOCUMENT & EVIDENCE RULES

Domain: Documents / Evidence (Cluster E). Rule IDs: `DOC-*`.

**Document engine status (audit §15.2):** Module 7 generates PDFs (RFQ, BAST, invoice). These are internal/support documents, clearly labelled, never presented as official procurement instruments (GOV-01/DOC-03).

## DOC-01 — Legal Documents per Transaction

| Field | Value |
|---|---|
| Rule ID | DOC-01 |
| Rule Name | Legal documents per transaction (RFQ, order, invoice, BAST, payment) |
| Domain | Documents / Evidence |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL (PDF engine exists) |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024; PP 71/2019 |
| Article | Ps 5; Ps 22(1) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Complete document set per transaction |
| Automation Class | DETERMINISTIC (document checklist) |
| Enforcement | BLOCK (incomplete checklist) |
| Module | 7 (Documents) |
| System Impact | Evidence/document registry; `document_evidence` entity |
| Testing | PROPOSED — feature test: document checklist completeness |
| Dependencies | affects: ETS-02, GOV-01 |

## DOC-02 — Electronic Record Integrity & Non-Repudiation

| Field | Value |
|---|---|
| Rule ID | DOC-02 |
| Rule Name | Electronic record integrity, authenticity & retrievability |
| Domain | Documents / Evidence |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU ITE 11/2008 jo 1/2024 |
| Article | Ps 5 (records); Ps 11 (signatures — see B2B-03/B2B-04) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Integrity hashes + authenticity-preserving storage; records retrievable — statutory basis: admissibility of electronic records (UU ITE Ps 5). Cryptographic non-repudiation applies ONLY via certified TTE / certificate mechanisms (B2B-04, PSE-CERT-001); NOT required for every electronic record |
| Automation Class | DETERMINISTIC (integrity) |
| Enforcement | BLOCK (tamper detected) |
| Module | 7 (Documents) / 8 (Audit Logs) |
| Testing | PROPOSED — integration test: integrity verification |
| Dependencies | affects: B2B-02, B2B-03 |

## DOC-03 — Document Labelling (Mirror vs Original)

| Field | Value |
|---|---|
| Rule ID | DOC-03 |
| Rule Name | Document labelling (mirror vs original; official instrument disclaimer) |
| Domain | Documents / Evidence |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018; GOV-01 |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Label documents mirror vs original (AJM safeguard) so internal mirrored records are never presented as original government instruments (legal/procurement integrity). Disclaimer wording = AJM policy, not mandated text |
| Forbidden Behavior | Presenting internal mirrored records as original government instruments |
| Automation Class | DETERMINISTIC + HUMAN REVIEW (template approval) |
| Enforcement | REQUIRE_REVIEW |
| Module | 7 (Documents) |
| System Impact | Labelling/interceptor |
| Testing | PROPOSED — feature test: labelling enforcement |
| Dependencies | depends_on: GOV-01; affects: PROH-01 |

## DOC-04 — Evidence Retention

| Field | Value |
|---|---|
| Rule ID | DOC-04 |
| Rule Name | Evidence retention (see §19 retention matrix) |
| Domain | Documents / Evidence |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Tax/PDP/contract rules (document-specific) |
| Article | Per-document basis (§19) |
| Official Source | jdih.kemenkeu.go.id / jdih.komdigi.go.id (per document domain) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (retention rules are document-specific; §19) |
| Required Behavior | Retention policy engine (tax/PDP/contract/evidence matrix) — deterministic enforcement ONLY after the applicable retention rule is resolved |
| Forbidden Behavior | Universal `retention_years = 10` or `= 30` default; deterministic deletion via a universal "longest lawful basis" formula when retention rules conflict or are unresolved |
| Automation Class | DETERMINISTIC (policy engine, resolved basis) + REQUIRE_REVIEW (conflicts/unresolved) |
| Enforcement | REQUIRE_REVIEW (conflict/unresolved) / BLOCK (violation of resolved basis) |
| Module | 7 (Documents) |
| System Impact | Retention policy engine; `retention_policies` entity |
| Testing | PROPOSED — feature test: per-document retention |
| Dependencies | affects: PROH-08; §19 retention matrix |

## DOC-05 — Template Versioning & Legal Review Control

| Field | Value |
|---|---|
| Rule ID | DOC-05 |
| Rule Name | Template versioning & legal review control |
| Domain | Documents / Evidence |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | General (internal governance) |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (as recommended control) |
| Required Behavior | Versioned templates with legal-review sign-off |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | REQUIRE_REVIEW (template change) |
| Module | 7 (Documents) |
| Testing | PROPOSED — feature test: template version history |
| Dependencies | affects: GOV-01, DOC-03 |

---

# 14. ROLES & AUTHORITY RULES

Domain: Roles / Authority (Cluster E). Rule IDs: `ROLE-*`.

## ROLE-01 — System Admin (Platform)

| Field | Value |
|---|---|
| Rule ID | ROLE-01 |
| Rule Name | System Admin (platform) |
| Domain | Roles / Authority |
| Priority | LOW |
| Reg. Applicability | REQUIRED |
| System Readiness | IMPLEMENTED |
| Real-World Compliance | UNVERIFIED |
| Regulation | Internal |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (internal) |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | LOG_ONLY |
| Module | 1 (Auth) |
| Testing | PROPOSED — existing RBAC covered by Module 1 |
| Dependencies | affects: SEC-03 |

## ROLE-02 — Data Protection Officer (DPO) / PDP Function Officer

| Field | Value |
|---|---|
| Rule ID | ROLE-02 |
| Rule Name | DPO / PDP function officer |
| Domain | Roles / Authority |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 53–54 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional on PDP-DPO-001 trigger) |
| Required Behavior | DPO role capability + assignment record |
| Automation Class | PROFESSIONAL LEGAL/TAX REVIEW (trigger) + GOVERNANCE (record) |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config) |
| System Impact | `dp_roles` entity |
| Testing | PROPOSED — feature test: DPO assignment record |
| Dependencies | depends_on: PDP-DPO-001 |

## ROLE-03 — Privacy Officer (if DPO not triggered)

| Field | Value |
|---|---|
| Rule ID | ROLE-03 |
| Rule Name | Privacy Officer |
| Domain | Roles / Authority |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Internal best practice |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (capability, not statutory role) |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | LOG_ONLY |
| Module | 0 (Config) |
| Testing | PROPOSED — feature test: privacy-officer capability |
| Dependencies | — |

## ROLE-04 — Compliance / Legal Reviewer

| Field | Value |
|---|---|
| Rule ID | ROLE-04 |
| Rule Name | Compliance / Legal reviewer |
| Domain | Roles / Authority |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Internal |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (internal) |
| Required Behavior | Approve templates/channels |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config) |
| Testing | PROPOSED — feature test: reviewer approval gate |
| Dependencies | affects: GOV-01, DOC-05 |

## ROLE-05 — Tax Officer

| Field | Value |
|---|---|
| Rule ID | ROLE-05 |
| Rule Name | Tax officer (faktur/withholding) |
| Domain | Roles / Authority |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | DJP obligations |
| Article | N/A |
| Official Source | pajak.go.id |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (conditional) |
| Required Behavior | Tax officer role for faktur/withholding actions |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | REQUIRE_REVIEW |
| Module | 0 (Config) / 5 (Payments) |
| Testing | PROPOSED — feature test: tax-officer role |
| Dependencies | affects: TAX-PPN-03 |

## ROLE-06 — Auditor / Audit-Log Viewer

| Field | Value |
|---|---|
| Rule ID | ROLE-06 |
| Rule Name | Audit-log viewer (RBAC capability) |
| Domain | Roles / Authority |
| Priority | MEDIUM |
| Reg. Applicability | RECOMMENDED (capability only, NOT statutory role) — PP 71/2019 Ps 22(1) mandates the audit trail (PSE-AUDIT-001); the "auditor" role is an RBAC implementation choice |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 (underlying obligation via PSE-AUDIT-001) |
| Article | Ps 22(1) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (audit-trail obligation); auditor role = governance choice |
| Automation Class | DETERMINISTIC (audit-log access) |
| Enforcement | LOG_ONLY |
| Module | 8 (Audit Logs) |
| Testing | PROPOSED — feature test: audit-log viewer access |
| Dependencies | depends_on: PSE-AUDIT-001 |

## ROLE-07 — Consent Manager (capability)

| Field | Value |
|---|---|
| Rule ID | ROLE-07 |
| Rule Name | Consent manager |
| Domain | Roles / Authority |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED (capability only, NOT statutory role) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 (capability) |
| Article | N/A |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (capability) |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | LOG_ONLY |
| Module | 0 (Config) |
| Testing | PROPOSED — feature test: consent-manager capability |
| Dependencies | affects: PDP-RIGHT-005 |

## ROLE-08 — Breach Response Owner (capability)

| Field | Value |
|---|---|
| Rule ID | ROLE-08 |
| Rule Name | Breach response owner |
| Domain | Roles / Authority |
| Priority | LOW |
| Reg. Applicability | RECOMMENDED (capability only, NOT statutory role) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 46 (capability basis) |
| Official Source | N/A |
| Verification Date | 13-Aug-2026 |
| Confidence | CONFIRMED (capability) |
| Automation Class | GOVERNANCE / MONITORING |
| Enforcement | LOG_ONLY |
| Module | 0 (Config) |
| Testing | PROPOSED — feature test: breach-response owner capability |
| Dependencies | affects: INC-PDP-001 |

---

# 15. INCIDENT RULES

Domain: Incident Management (Cluster E/C). Rule IDs: `INC-*`.

**PSE / PDP incident separation (mandatory):** Keep distinct — **Personal Data Breach (INC-PDP-001) vs Serious Electronic System Disruption (INC-PSE-001) vs Other PDP/PSE failures (INC-PSE-002)**. One incident engine may implement them, but the legal rule, deadline, recipient, evidence, and escalation path must be distinct.

## INC-PSE-001 — Serious System Disruption Reporting

| Field | Value |
|---|---|
| Rule ID | INC-PSE-001 |
| Rule Name | Serious system disruption reporting |
| Domain | Incident Management (PSE) |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | **Ps 24(3)** |
| Official Source | jdih.komdigi.go.id (`view/id/695`) |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Disruption reporting flow + notification channel |
| Automation Class | DETERMINISTIC (reporting workflow) |
| Enforcement | REQUIRE_REVIEW |
| Module | 6 (Notifications) |
| System Impact | Breach/incident service; `incident_register` entity |
| Testing | PROPOSED — feature test: disruption report workflow |
| Dependencies | affects: SEC-02, INC-PSE-002 |

## INC-PSE-002 — PDP Failure in PSE (Incident Class)

| Field | Value |
|---|---|
| Rule ID | INC-PSE-002 |
| Rule Name | PDP failure in PSE — separate incident class |
| Domain | Incident Management (PSE/PDP) |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | PP 71/2019 |
| Article | Ps 14(5) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Required Behavior | Separate incident class for PDP failure in PSE |
| Automation Class | DETERMINISTIC (classification) + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 6 (Notifications) |
| Testing | PROPOSED — feature test: incident class distinction |
| Dependencies | must remain distinct from INC-PDP-001 (see §15) |

## INC-PDP-001 — Personal Data Breach Incident Classification

| Field | Value |
|---|---|
| Rule ID | INC-PDP-001 |
| Rule Name | Personal data breach incident classification |
| Domain | Incident Management (PDP) |
| Priority | HIGH |
| Reg. Applicability | REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | **Ps 46** |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED — distinct object from PDP-BREACH-001 (obligation vs incident classification) |
| Required Behavior | Classify personal-data-breach incidents; distinct legal rule, deadline, recipient, evidence, escalation from PSE disruptions |
| Automation Class | DETERMINISTIC (classification) + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 6 (Notifications) |
| System Impact | Breach/incident service; `incident_register` entity |
| Testing | PROPOSED — feature test: breach classification |
| Dependencies | affects: PDP-BREACH-001, PDP-3X24-003, SEC-02 |

---

# 16. PROHIBITED BEHAVIORS

Domain: Prohibited Behaviors (Cluster E). Rule IDs: `PROH-*`.

## PROH-01 — No `ORD-*` Presented as Official PO

| Field | Value |
|---|---|
| Rule ID | PROH-01 |
| Rule Name | Presenting internal `ORD-*` docs as official PO is prohibited |
| Domain | Prohibited Behaviors |
| Priority | HIGH |
| Reg. Applicability | REQUIRED (prohibit) |
| System Readiness | PARTIAL (labelling control) |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018; GOV-01 |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Forbidden Behavior | Presenting `ORD-*` as official PO |
| Automation Class | DETERMINISTIC (labelling interceptor) |
| Enforcement | BLOCK |
| Module | 2 (Orders) / 7 (Documents) |
| System Impact | Labelling/interceptor |
| Testing | PROPOSED — feature test: ORD-* not presented as PO |
| Dependencies | depends_on: GOV-01, DOC-03 |

## PROH-02 — No Non-Catalog Offer as E-Catalog Offer

| Field | Value |
|---|---|
| Rule ID | PROH-02 |
| Rule Name | Presenting non-catalog offers as e-catalog offers is prohibited |
| Domain | Prohibited Behaviors |
| Priority | HIGH |
| Reg. Applicability | REQUIRED (prohibit) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Kepka LKPP 122/2022 |
| Article | e-Purchasing rules |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Forbidden Behavior | Non-catalog offers presented as e-catalog |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | BLOCK / REQUIRE_REVIEW |
| Module | 2 (Orders) |
| Testing | PROPOSED — feature test: non-catalog mislabel blocked |
| Dependencies | depends_on: LKPP-06, LKPP-05 |

## PROH-03 — No TKDN/SNI Claim Without Evidence

| Field | Value |
|---|---|
| Rule ID | PROH-03 |
| Rule Name | Claiming TKDN/SNI without evidence is prohibited |
| Domain | Prohibited Behaviors |
| Priority | HIGH |
| Reg. Applicability | REQUIRED (prohibit) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Perpres 16/2018 |
| Article | General |
| Official Source | jdih.lkpp.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Forbidden Behavior | TKDN/SNI claim without recorded evidence |
| Automation Class | DETERMINISTIC (evidence check) + HUMAN (authenticity) |
| Enforcement | BLOCK (claim without evidence) / REQUIRE_REVIEW (authenticity) |
| Module | 3 (Products) |
| Testing | PROPOSED — feature test: evidence-gated claim |
| Dependencies | depends_on: TKDN-01, TKDN-02 |

## PROH-04 — No Processing Beyond Purpose

| Field | Value |
|---|---|
| Rule ID | PROH-04 |
| Rule Name | Processing data beyond purpose (no purpose limitation) is prohibited |
| Domain | Prohibited Behaviors |
| Priority | HIGH |
| Reg. Applicability | REQUIRED (prohibit) |
| System Readiness | PARTIAL |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 16, 19 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Forbidden Behavior | Processing beyond stated purpose |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — compliance test: purpose limitation |
| Dependencies | affects: PSE-DATA-001, SEC-01, SEC-07 |

## PROH-05 — No Processing After Consent Withdrawal

| Field | Value |
|---|---|
| Rule ID | PROH-05 |
| Rule Name | Processing after consent withdrawal (3×24h) is prohibited |
| Domain | Prohibited Behaviors |
| Priority | HIGH |
| Reg. Applicability | REQUIRED (prohibit) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 40(2) |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Forbidden Behavior | Processing after consent-withdrawal deadline |
| Automation Class | DETERMINISTIC (timer) |
| Enforcement | `STOP_PROCESSING` (AJM system-response; via PDP-3X24-001) |
| Module | 4 (Companies) / 6 (Notifications) |
| Testing | PROPOSED — feature test: consent-withdrawal block |
| Dependencies | depends_on: PDP-3X24-001 |

## PROH-06 — No Auto-Decisions on Data Subjects Without Safeguards

| Field | Value |
|---|---|
| Rule ID | PROH-06 |
| Rule Name | Auto-decisions on data subjects w/o safeguards (no profiling without basis) is prohibited |
| Domain | Prohibited Behaviors |
| Priority | MEDIUM |
| Reg. Applicability | CONDITIONALLY REQUIRED |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 10 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED (conditional) |
| Forbidden Behavior | Automated decisions/profiling without legal basis & safeguards |
| Automation Class | DETERMINISTIC + HUMAN REVIEW |
| Enforcement | REQUIRE_REVIEW |
| Module | 4 (Companies) |
| Testing | PROPOSED — feature test: profiling safeguard gate |
| Dependencies | affects: PDP-RIGHT-006 |

## PROH-07 — No Non-Notification of Breaches

| Field | Value |
|---|---|
| Rule ID | PROH-07 |
| Rule Name | Non-notification of breaches is prohibited |
| Domain | Prohibited Behaviors |
| Priority | HIGH |
| Reg. Applicability | REQUIRED (prohibit) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | UU 27/2022 |
| Article | Ps 46 |
| Official Source | jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Forbidden Behavior | Failing to notify per breach obligations |
| Automation Class | DETERMINISTIC (timer) |
| Enforcement | `ESCALATION_VIOLATION_AUDIT` (AJM system-response; via PDP-3X24-003 — not a generic processing BLOCK) |
| Module | 6 (Notifications) |
| Testing | PROPOSED — feature test: breach notification enforcement |
| Dependencies | depends_on: PDP-3X24-003, INC-PDP-001 |

## PROH-08 — No Retention Beyond Lawful Basis

| Field | Value |
|---|---|
| Rule ID | PROH-08 |
| Rule Name | No retention beyond a resolved lawful basis |
| Domain | Prohibited Behaviors |
| Priority | HIGH |
| Reg. Applicability | REQUIRED (prohibit) |
| System Readiness | MISSING |
| Real-World Compliance | UNVERIFIED |
| Regulation | Tax/PDP/contract |
| Article | Per-document basis (§19) |
| Official Source | jdih.kemenkeu.go.id / jdih.komdigi.go.id |
| Verification Date | 13-Aug-2026 |
| Effective | In force |
| Confidence | CONFIRMED |
| Forbidden Behavior | Retaining beyond a resolved lawful basis (§19, each basis independently verified). When retention rules conflict or are unresolved → REQUIRE_REVIEW — NO deterministic "longest lawful basis" deletion formula |
| Automation Class | DETERMINISTIC (policy engine, resolved basis) / REQUIRE_REVIEW (conflict or unresolved) |
| Enforcement | BLOCK (violation of resolved basis) / REQUIRE_REVIEW (conflict or unresolved) |
| Module | 7 (Documents) |
| System Impact | Retention policy engine |
| Testing | PROPOSED — feature test: retention enforcement |
| Dependencies | depends_on: DOC-04; §19 retention matrix |

---

# 17. AUTOMATION RULES

Primary automation classification for every executable/quasi-executable rule (from audit §24.2 and §17 classification):

| Automation Class | Meaning | Rules |
|---|---|---|
| **DETERMINISTIC** | Computed from verified inputs | PDP-3X24-001/002/003, TAX-PPN-01 (calc), TAX-PPN-02 (code selection), TAX-PPN-05, DOC-01 (checklist), TKDN-03 (expiry), ETS-01, ETS-02, B2B-01, B2B-05, LKPP-02 (category mapping), LKPP-04, PSE-AUDIT-001, ROLE-06, SEC-03, SEC-04, DOC-02, INC-PSE-001, PROH-05, PROH-07 |
| **DETERMINISTIC + HUMAN REVIEW** | System determines normal cases; exceptions need review | GOV-01, GOV-02, GOV-03, GOV-04, TAX-PPN-03, TAX-PPN-04, TAX-PPH-01, TAX-PPH-02, B2B-03, B2B-04, LKPP-01, LKPP-03, LKPP-05, LKPP-06, TKDN-01, TKDN-02, TKDN-04, PSE-GOV-001, PSE-GOV-002, PSE-DATA-001, PSE-DATA-002, PSE-SEC-001, PSE-CERT-001, SEC-01, SEC-02, SEC-05, SEC-06, SEC-07, PDP-PROC-001, PDP-RIGHT-001..007, PDP-RIGHT-009, PDP-DPO-002, DOC-03, DOC-04, INC-PSE-002, INC-PDP-001, PROH-02, PROH-03, PROH-04, PROH-06 |
| **HUMAN DECISION** | System provides evidence; authorized human decides | (none currently assigned — preserved as not asserted) |
| **PROFESSIONAL LEGAL/TAX REVIEW** | System must not make the legal decision | TAX-PPH-03, PSE-DATA-003, PMSE-04, PDP-DPO-001, ROLE-02 (trigger) |
| **GOVERNANCE / MONITORING** | Monitoring, change management, compliance observation | GOV-05, PSE-REG-001/002/003, PSE-SANC-001, ROLE-01, ROLE-03, ROLE-04, ROLE-05, ROLE-07, ROLE-08, DOC-05, PDP-RIGHT-008, TAX-CRT-01 |

**Automation candidates (audit §24.2):** tax DPP calc (deterministic), faktur code selection (deterministic hierarchy), procurement-channel warnings (deterministic + PPK nuance), document checklist (deterministic), TKDN/SNI expiry (deterministic) vs authenticity (human), PSE/PDP controls (partial, human), 3×24h timers (deterministic), consent-withdrawal timers (deterministic), transaction-evidence validation (deterministic integrity), audit-trail controls (deterministic logging).

**Safety rule:** No unresolved legal interpretation becomes deterministic blocking logic. UNCERTAIN → `REQUIRE_REVIEW` / `PROFESSIONAL_LEGAL_TAX_REVIEW`, never an automatic legal decision.

---

# 18. HUMAN REVIEW RULES

For every non-deterministic rule, the following is defined:

| Field | Definition |
|---|---|
| Why human review is required | Legal interpretation is fact-specific or unresolved (per audit) |
| Authorized reviewer | ROLE-04 (Compliance/Legal reviewer), ROLE-05 (Tax officer), or designated professional |
| Evidence presented | Full rule context: basis, status, inputs, audit references |
| Allowed decisions | Approve / reject / escalate to professional legal/tax review / record as unresolved |
| Audit trail | Decision recorded with timestamp and reviewer identity |
| Decision timestamp | Mandatory |
| Decision expiry / re-review trigger | Annual re-verification (§20) or on regulatory change |

**Rules requiring human review (non-exhaustive, primary):** GOV-01, GOV-02, GOV-03, GOV-04, LKPP-01, LKPP-03, LKPP-05, LKPP-06, TKDN-01, TKDN-02, TKDN-04, B2B-03, B2B-04, PSE-GOV-001/002, PSE-DATA-001/002, PSE-SEC-001, PSE-CERT-001 (issuance), TAX-PPN-03, TAX-PPN-04, TAX-PPH-01, TAX-PPH-02, PDP-PROC-001, PDP-RIGHT-001..007, PDP-RIGHT-009, PDP-DPO-002, SEC-01/02/05/06/07, DOC-03, DOC-04, INC-PSE-002, INC-PDP-001, PROH-02/03/04/06.

**Professional legal/tax review rules (system must NOT decide):** TAX-PPH-03, PSE-DATA-003, PMSE-04, PDP-DPO-001, ROLE-02 (trigger). These produce `REQUIRE_REVIEW` / `PROFESSIONAL_LEGAL_TAX_REVIEW`, never an automatic legal decision.

**Default enforcement for human-review rules:** `REQUIRE_REVIEW` (not silently allow or block) unless the audit explicitly requires otherwise.

---

# 19. RETENTION RULES

**Rule (audit §19b):** retention = longest lawful basis, each basis independently verified; no universal 30-year civil retention; no blanket `retention_years = 10` default. PDP basis must be independently documented (lawful basis, purpose, retention rationale). Where tax retention, PDP minimization, contractual evidence, and another legal regime appear to conflict → **`REQUIRES LEGAL REVIEW`** — never silently choose the most conservative number and label it a legal requirement.

| Document / Record | Tax Retention | PDP Retention | Contract/Evidence | Retention Rule (composite) | Confidence |
|---|---|---|---|---|---|
| Invoice & faktur pajak (E-Faktur) | 10 years (tax books; UU KUP) | Reasonable per PDP (business justification) | Contract/evidence | **10 years (tax drives); verify PDP lawfulness** | CONFIRMED (tax basis) |
| Payment records / bank statements | 10 years | Reasonable | Evidence | **10 years** | CONFIRMED (tax basis) |
| RFQ / order / PO | Business records (5–10 yr per applicable reg) | Reasonable | 5 years civil limitation (KUHPdt 30-yr caveat for some claims — `REQUIRES PROFESSIONAL LEGAL REVIEW`) | **UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE (no blanket default)** | UNCERTAIN |
| BAST / delivery evidence | Business records | Reasonable | Evidence | **UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE** | UNCERTAIN |
| PSE audit logs (Ps 22(1)) | N/A | N/A | PSE operational | **Retain per PSE ops continuity; at least statutory/audit period** | CONFIRMED (concept) |
| Consent records (PDP) | N/A | Proof of consent — retain while processing + transition | Evidence of legal basis | **Retain until processing ends + grace; document in ROPA-style record** | CONFIRMED (concept) |
| Data subject request records | N/A | PDP Ps 14 procedures | Evidence | **Per request lifecycle + reasonable** | CONFIRMED (concept) |
| TKDN/SNI certificates | N/A | N/A | Government procurement evidence | **Retain for procurement/audit cycles** | CONFIRMED (concept) |
| Financial/tax records (PPN) | 10 years | Reasonable | Evidence | **10 years** | CONFIRMED (tax basis) |
| Employee/PIC personal data | N/A | PDP lawful basis | Contract | **End of relationship + statutory grace** | CONFIRMED (concept) |

**Retention rule fields tracked per rule:** Document Type, Legal Domain, Purpose, Retention Requirement, Start Event, End Event, Legal Basis, Confidence, Personal-data impact.

**Enforcement:** `DOC-04` / `PROH-08` use a retention policy engine — no universal default.

---

# 20. REGULATORY CHANGE MANAGEMENT

**Mechanism (audit §25.2):** versioned `tax_rules` and `regulatory_reference` tables; effective-dated; each change signed off by professional reviewer; changelog entries referencing source. Annual (or trigger-based) re-verification of all `CONFIRMED` citations.

**Process:**

```
MONITOR
↓
IDENTIFY CHANGE
↓
VERIFY PRIMARY SOURCE
↓
ASSESS APPLICABILITY
↓
CREATE / UPDATE RULE
↓
SET EFFECTIVE DATE
↓
CREATE / UPDATE TESTS
↓
UPDATE SYSTEM
↓
VERIFY
↓
AUDIT
```

**No regulation change is implemented directly in code without a corresponding rule update.**

**Tracked watch items (audit §25.1, 13-Aug-2026):**

| # | Item | Status | Monitor source |
|---|---|---|---|
| 1 | PDP implementing regulation (PP) | PENDING | jdih.komdigi.go.id |
| 2 | Permenkominfo 11/2022 amendments / successor | NONE FOUND (in force) | jdih.komdigi.go.id |
| 3 | PPN DPP nilai lain / rate adjustments | MONITOR | jdih.kemenkeu.go.id |
| 4 | Coretax rollout timeline | MONITOR | pajak.go.id |
| 5 | e-Catalog version updates (Catalog V6+ T&C) | MONITOR | catalog.lkpp.go.id |
| 6 | Permendag 31/2023 successors | MONITOR | jdih.kemendag.go.id |
| 7 | Perpres 46/2025 follow-on implementing rules | MONITOR | jdih.lkpp.go.id |
| 8 | PSE registration portal & PSrE recognition list | MONITOR | pse.kominfo.go.id / tte.komdigi.go.id |

**Effective-date support:** every time-sensitive rule supports `effective_from`, `effective_until`, `source_version`, `verification_date`, `rule_version`. Historical transactions preserve the resolved rule snapshot (especially Tax §10, procurement §4–6, documents §13, PDP retention §19).

---

# 21. RULE → SYSTEM TRACEABILITY

Current Modules 1–8 status is **not rewritten**; derived from audit §19a/§20. Only `IMPLEMENTED` / `PARTIAL` / `MISSING` / mapping / remediation are identified.

| Rule ID | Current Module | Future Module | Component / Service | Data Entity | Status |
|---|---|---|---|---|---|
| GOV-01 | 7 (Documents) | 9 | Labelling interceptor | document templates | PARTIAL — remediation required (no labelling enforcement) |
| GOV-02 | 4 (Companies) | 9 | Supplier-eligibility service | `supplier_eligibility` | MISSING |
| GOV-03 | 2 (Orders) | 9 | Procurement-channel abstraction | order metadata | PARTIAL — remediation required |
| GOV-04 | 2 (Orders) | 9 | Labelling/interceptor | order status labels | PARTIAL — remediation required |
| GOV-05 | 0 (Config) | — | ToS | — | PARTIAL (mapping/documentation) |
| LKPP-01 | 2 (Orders) | 9 | Channel abstraction; warnings | order metadata | PARTIAL — remediation required |
| LKPP-02 | 3 (Products) | 9 | Category gap matrix | product categories | MISSING |
| LKPP-03 | 2 (Orders) | 9 | Channel abstraction | method mapping | MISSING |
| LKPP-04 | 0/3 | 9 | ToS/checklist | acknowledgment records | MISSING |
| LKPP-05 | 3 (Products) | 9 | Catalog status tracking | catalog_status | MISSING |
| LKPP-06 | 2 (Orders) | 9 | Integrity control | order/catalog links | MISSING |
| TKDN-01 | 3 (Products) | 9 | Product certifications | `product_certifications` | MISSING |
| TKDN-02 | 3 (Products) | 9 | Product certifications | `product_certifications` | MISSING |
| TKDN-03 | 3 (Products) | 9 | Expiry alerts | certificate expiry | MISSING |
| TKDN-04 | 3 (Products) | 9 | Procurement-channel warning | offer→catalog status | MISSING |
| TKDN-05 | 3 (Products) | — | Display-only % | certificate data | MISSING (display only) |
| B2B-01 | 8 (Audit Logs) | 9 | Consent capture | audit log | PARTIAL — strengthen |
| B2B-02 | 8 (Audit Logs) | 9 | Record integrity | audit log | PARTIAL |
| B2B-03 | 8/7 | 9 | Signature checklist | signature records | PARTIAL — cautious wording |
| B2B-04 | 7 (Documents) | 9 | Certified TTE module | certificate registry | MISSING |
| B2B-05 | 0 (Config) | — | ToS acceptance | — | PARTIAL |
| ETS-01 | 8 (Audit Logs) | 9 | Timestamp integrity | audit log | PARTIAL |
| ETS-02 | 8 (Audit Logs) | 9 | Record preservation | audit log | PARTIAL |
| ETS-03 | 8 (Audit Logs) | 9 | Consent capture | audit log | PARTIAL |
| PSE-REG-001 | 0 (Config) | 9 | PSE registry | `pse_registration` | MISSING |
| PSE-REG-002 | 0 (Config) | 9 | PSE registry | `pse_registration` | MISSING |
| PSE-REG-003 | 0 (Config) | 9 | PSE registry | `pse_registration` | MISSING |
| PSE-GOV-001 | 1–8 | 9 | Platform ops | — | PARTIAL |
| PSE-GOV-002 | 1–8 | 9 | Continuity | — | PARTIAL |
| PSE-GOV-003 | 1–8 | 9 | User assistance | — | PARTIAL |
| PSE-DATA-001 | 4 (Companies) | 9 | PDP service | — | PARTIAL |
| PSE-DATA-002 | 0 (Config) | 9 | Data residency | — | UNVERIFIED |
| PSE-DATA-003 | 0 (Config) | 9 | Classification procedure | — | UNVERIFIED — legal review |
| PSE-AUDIT-001 | 8 (Audit Logs) | 9 | Audit-trail adapter | `audit_trail_mapping` | PARTIAL — map Module 8 → Ps 22(1) |
| PSE-SEC-001 | 1–8 | 9 | Security assurance | — | PARTIAL |
| PSE-CERT-001 | 0 (Config) | 9 | Certificate registry | `pse_certificates` | MISSING |
| PSE-SANC-001 | 0 (Config) | — | Awareness record | — | NOT APPLICABLE (awareness) |
| INC-PSE-001 | 6 (Notifications) | 9 | Breach/incident service | `incident_register` | MISSING |
| INC-PSE-002 | 6 (Notifications) | 9 | Breach/incident service | `incident_register` | MISSING |
| PMSE-01 | 4 (Companies) | 9 | Supplier-eligibility service | supplier license | MISSING |
| PMSE-02 | 4 (Companies) | 9 | Supplier-eligibility service | NIB-based record | MISSING |
| PMSE-03 | 8 (Audit Logs) | 9 | Transaction integrity | audit log | PARTIAL |
| PMSE-04 | 0 (Config) | — | Classification guard | — | NOT APPLICABLE (conditional) |
| TAX-PPN-01 | 5 (Payments) | 9 | TaxRule engine | `tax_rules` | PARTIAL — DPP calc engine required |
| TAX-PPN-02 | 5 (Payments) | 9 | TaxRule engine | `faktur_codes` | MISSING |
| TAX-PPN-03 | 5 (Payments) | 9 | TaxRule engine; filing | — | MISSING |
| TAX-PPN-04 | 5 (Payments) | 9 | TaxRule engine | counterparty class | MISSING |
| TAX-PPN-05 | 4 (Companies) | 9 | Identity validation | counterparty identity | MISSING |
| TAX-PPN-06 | N/A | — | Excluded | — | NOT APPLICABLE |
| TAX-PPH-01 | 5 (Payments) | 9 | TaxRule engine (PPh flags) | `tax_rules` | MISSING |
| TAX-PPH-02 | 5 / HR | 9 | TaxRule engine | — | MISSING |
| TAX-PPH-03 | 4 (Companies) | 9 | Identity evidence (basis pending) | — | MISSING — legal review |
| TAX-CRT-01 | 0 (Config) | 9 | Integration decision | — | MISSING (optional) |
| PDP-RIGHT-001..009 | 4 (Companies) | 9 | PDP service | `data_subject_requests` | MISSING |
| PDP-PROC-001 | 4 (Companies) | 9 | PDP service | `data_subject_requests` | MISSING |
| PDP-BREACH-001 | 6 (Notifications) | 9 | Breach/incident service | `breach_notifications` | MISSING |
| PDP-3X24-001 | 6 (Notifications) | 9 | Statutory timers | timers | MISSING |
| PDP-3X24-002 | 6 (Notifications) | 9 | Statutory timers | timers | MISSING |
| PDP-3X24-003 | 6 (Notifications) | 9 | Statutory timers | timers | MISSING |
| PDP-DPO-001 | 0 (Config) | 9 | `dp_roles` | `dp_roles` | MISSING |
| PDP-DPO-002 | 4 (Companies) | 9 | Processor agreements | — | MISSING |
| SEC-01..07 | 1–8 (cross) | 9 | Security governance | — | PARTIAL (01/03/04/05/07), MISSING (02/06) |
| DOC-01 | 7 (Documents) | 9 | Evidence/document registry | `document_evidence` | PARTIAL (PDF engine exists) |
| DOC-02 | 7/8 | 9 | Integrity hashing | `document_evidence` | PARTIAL |
| DOC-03 | 7 (Documents) | 9 | Labelling/interceptor | document templates | PARTIAL |
| DOC-04 | 7 (Documents) | 9 | Retention policy engine | `retention_policies` | MISSING |
| DOC-05 | 7 (Documents) | 9 | Template control | templates | MISSING |
| ROLE-01 | 1 (Auth) | — | RBAC | — | IMPLEMENTED |
| ROLE-02 | 0 (Config) | 9 | `dp_roles` | `dp_roles` | MISSING |
| ROLE-03 | 0 (Config) | 9 | Role capability | — | MISSING |
| ROLE-04 | 0 (Config) | 9 | Reviewer role | — | MISSING |
| ROLE-05 | 0/5 | 9 | Tax-officer role | — | MISSING |
| ROLE-06 | 8 (Audit Logs) | 9 | Auditor role | — | PARTIAL |
| ROLE-07 | 0 (Config) | 9 | Consent-manager capability | — | MISSING |
| ROLE-08 | 0 (Config) | 9 | Breach-response capability | — | MISSING |
| PROH-01 | 2/7 | 9 | Labelling/interceptor | — | PARTIAL |
| PROH-02 | 2 (Orders) | 9 | Integrity control | — | MISSING |
| PROH-03 | 3 (Products) | 9 | Evidence-gated claim | `product_certifications` | MISSING |
| PROH-04 | 4 (Companies) | 9 | Purpose-limitation controls | — | PARTIAL |
| PROH-05 | 4/6 | 9 | Consent-withdrawal block | timers | MISSING |
| PROH-06 | 4 (Companies) | 9 | Profiling safeguard | — | MISSING |
| PROH-07 | 6 (Notifications) | 9 | Breach-notification enforcement | timers | MISSING |
| PROH-08 | 7 (Documents) | 9 | Retention enforcement | `retention_policies` | MISSING |
| INC-PDP-001 | 6 (Notifications) | 9 | Breach/incident service | `incident_register` | MISSING |

**Module 1–8 audit note (audit §20.2):** This Rulebook is read-only for Modules 1–8. All proposals above are **Module 9+ design inputs**.

---

# 22. RULE → TEST TRACEABILITY

**Honesty rule:** No test is claimed as existing unless implemented. All entries below are **`PROPOSED`**.

| Rule ID | Test Type | Test Name / Proposed Test | Current Status | Required Environment | Notes |
|---|---|---|---|---|---|
| GOV-01 | Feature | generated PDFs carry label + disclaimer | PROPOSED | PHPUnit + PDF engine | labelling enforcement |
| GOV-02 | Feature | supplier missing NIB flagged | PROPOSED | PHPUnit + DB | eligibility record |
| GOV-03 | Feature | each procurement method maps to workflow | PROPOSED | PHPUnit + DB | channel abstraction |
| GOV-04 | Feature | status labels distinguish internal vs official | PROPOSED | PHPUnit + DB | labelling |
| GOV-05 | Governance | ToS boundary clause present | PROPOSED | review | content review |
| LKPP-01 | Feature | cataloged product triggers e-purchasing default warning | PROPOSED | PHPUnit + DB | warning, not auto-select |
| LKPP-02 | Integration | category coverage report | PROPOSED | PHPUnit + DB | gap matrix |
| LKPP-03 | Feature | INAPROC method mapping | PROPOSED | PHPUnit + DB | method awareness |
| LKPP-04 | Feature | acknowledgment required | PROPOSED | PHPUnit + DB | T&C awareness |
| LKPP-05 | Integration | catalog status tracking | PROPOSED | PHPUnit + DB | URL ≠ proof of compliance |
| LKPP-06 | Feature | non-catalog offer flagged/blocked | PROPOSED | PHPUnit + DB | integrity control |
| TKDN-01/02 | Feature | TKDN/SNI evidence attributes required | PROPOSED | PHPUnit + DB | no auto-% |
| TKDN-03 | Unit | expiry computation | PROPOSED | PHPUnit | expiry alerts |
| TKDN-04 | Integration | warning on non-certified government offer | PROPOSED | PHPUnit + DB | channel warning |
| TKDN-05 | Regression | no auto-% computation | PROPOSED | PHPUnit | display only |
| B2B-01 | Feature | consent event recorded | PROPOSED | PHPUnit + DB | audit log |
| B2B-02 | Integration | record integrity check | PROPOSED | PHPUnit + DB | integrity |
| B2B-03 | Integration | six-criteria checklist present | PROPOSED | PHPUnit | non-boolean |
| B2B-04 | Feature | certified TTE signing path | PROPOSED | PHPUnit + tte service | conditional |
| B2B-05 | Feature | ToS acceptance required | PROPOSED | PHPUnit + DB | binding ToS |
| ETS-01 | Unit | timestamp integrity | PROPOSED | PHPUnit | tamper-evidence |
| ETS-02 | Compliance | record preservation | PROPOSED | PHPUnit + DB | admissible records |
| ETS-03 | Feature | consent per transaction | PROPOSED | PHPUnit + DB | capture |
| PSE-REG-001/002/003 | Feature | registration record + maintenance | PROPOSED | PHPUnit + DB | registry |
| PSE-GOV-001/002/003 | Compliance | reliability/continuity/assistance evidence | PROPOSED | integration | platform ops |
| PSE-DATA-001 | Compliance | PDP principles evidence | PROPOSED | integration | principles |
| PSE-DATA-002 | Feature | overseas-transfer safeguard flag | PROPOSED | PHPUnit + DB | not blanket |
| PSE-DATA-003 | Compliance | classification procedure | PROPOSED | review | fact-specific |
| PSE-AUDIT-001 | Compliance | Module 8 → Ps 22(1) mapping | PROPOSED | PHPUnit + DB | audit-trail adapter |
| PSE-SEC-001 | Compliance | uji kelayakan evidence | PROPOSED | integration | security assurance |
| PSE-CERT-001 | Feature | certificate record + expiry flag | PROPOSED | PHPUnit + DB | distinct from TTE |
| PSE-SANC-001 | Governance | sanctions awareness record | PROPOSED | review | awareness |
| INC-PSE-001/002 | Feature | disruption/PDP-failure report workflows | PROPOSED | PHPUnit + DB | distinct classes |
| PMSE-01/02 | Feature | license/NIB-based record visibility | PROPOSED | PHPUnit + DB | conditional |
| PMSE-03 | Integration | transaction integrity | PROPOSED | PHPUnit + DB | integrity |
| PMSE-04 | Governance | classification guard | PROPOSED | review | not assumed |
| TAX-PPN-01 | Unit | DPP nilai lain calc + versioned rate snapshot | PROPOSED | PHPUnit + TaxRule engine | no hardcoded rates |
| TAX-PPN-02 | Unit | faktur code selection per buyer class | PROPOSED | PHPUnit + `faktur_codes` | hierarchy |
| TAX-PPN-03 | Feature | E-Faktur issuance + SIAP data assembly | PROPOSED | PHPUnit + DB | reporting |
| TAX-PPN-04 | Feature | B2G collection path | PROPOSED | PHPUnit + DB | collector-status condition |
| TAX-PPN-05 | Unit | NPWP/NIK validation | PROPOSED | PHPUnit | identity |
| TAX-PPN-06 | Regression | no SPT Tahunan PPN references | PROPOSED | PHPUnit | excluded concept |
| TAX-PPH-01/02 | Feature | sell/buy-side PPh tracking; PPh 21 handling | PROPOSED | PHPUnit + DB | exact model → review |
| TAX-PPH-03 | Governance | basis resolution gate | PROPOSED | review | professional tax review |
| TAX-CRT-01 | Governance | integration decision record | PROPOSED | review | optional |
| PDP-RIGHT-001..009 | Feature | rights fulfillment flows (per right) | PROPOSED | PHPUnit + DB | distinct deadlines |
| PDP-PROC-001 | Feature | request intake workflow | PROPOSED | PHPUnit + DB | procedure, not right |
| PDP-BREACH-001 | Feature | breach notification flow | PROPOSED | PHPUnit + DB | obligation |
| PDP-3X24-001/002/003 | Unit | 3×24h timer computation | PROPOSED | PHPUnit | deterministic |
| PDP-DPO-001 | Feature | DPO trigger assessment | PROPOSED | PHPUnit + review | conditional |
| PDP-DPO-002 | Feature | processor agreement record | PROPOSED | PHPUnit + DB | duties |
| SEC-01..07 | Compliance | security governance controls | PROPOSED | integration | per rule |
| DOC-01 | Feature | document checklist completeness | PROPOSED | PHPUnit + DB | per transaction |
| DOC-02 | Integration | integrity verification | PROPOSED | PHPUnit + DB | hashes |
| DOC-03 | Feature | labelling enforcement | PROPOSED | PHPUnit + DB | mirror vs original |
| DOC-04 | Feature | per-document retention | PROPOSED | PHPUnit + retention engine | no universal default |
| DOC-05 | Feature | template version history | PROPOSED | PHPUnit + DB | versioning |
| ROLE-01..08 | Feature | role capabilities + assignment | PROPOSED | PHPUnit + DB | RBAC + records |
| PROH-01..08 | Feature | prohibited-behavior enforcement | PROPOSED | PHPUnit + DB | per rule |
| INC-PDP-001 | Feature | breach classification | PROPOSED | PHPUnit + DB | distinct incident |

---

# 23. PROFESSIONAL LEGAL/TAX REVIEW BACKLOG

All items preserved **unresolved** from audit §26 and §29.2. **Not resolved by guesswork.**

| # | Item | Type | Reason / Status |
|---|---|---|---|
| 1 | AJM exact classification under Permendag 31/2023 (Pedagang vs Retail Online vs platform) | Legal | Definitions are conditional & fact-specific |
| 2 | PDP implementing regulation effect on Ps 5–13, 40–41, 46 mechanics | Legal | Pending regulation |
| 3 | TKDN/SNI applicability to AJM's specific product lines | Legal/Procurement | Fact-specific, time-sensitive |
| 4 | PPh 23/21 treatment for the exact commercial model (sell/buy side) | Tax | Fact-specific |
| 5 | PPN treatment for specific goods/services (DPP nilai lain eligibility per product) | Tax | Fact-specific |
| 6 | Coretax integration approach (API scope, E-Faktur migration) | Tax | Business + regulator interface |
| 7 | Certified TTE adoption requirement by counterparties | Legal | Market-specific |
| 8 | Cross-border counterparty jurisdiction | Legal | Beyond Indonesian scope |
| 9 | Sertifikat Elektronik implementation path (PSrE selection, cost, ops) | Legal/Technical | PSE-CERT-001 details |
| 10 | B2G VAT collection scope (PMK 59/2022) for actual customer base | Tax | Customer-specific |
| 11 | Real-world status of AJM licenses/certificates/registrations | Legal | Requires AJM evidence |
| 12 | Non-certified TTE six-criteria assessment for AJM's flows | Legal | Requires implementation specifics |
| 13 | **TAX-PPH-03** — exact legal basis for counterparty tax-certificate/NPWP evidence | Tax | **UNCERTAIN — `REQUIRES PROFESSIONAL TAX REVIEW`** (PMK 131/2024 removed as incorrect citation) |
| 14 | **PMK 81/2024 amendment numbers** — Perubahan Pertama/Kedua not individually verified | Tax | Verified chain: PMK 54/2025 (Perubahan Ketiga), PMK 1/2026 (Perubahan Keempat) |
| 15 | PDP implementing regulation (UU 27/2022 transition) | Legal | `UNCERTAIN / LEGAL REVIEW` |
| 16 | Coretax integration approach | Tax | `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` |
| 17 | PMSE classification | Legal | `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` |
| 18 | TKDN/SNI supplier-evidence scope | Legal/Procurement | `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` |
| 19 | PPh 23 exact model | Tax | `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` |

**Status discipline (audit §27.3):** real-world AJM status for all obligations = **UNVERIFIED** (no assertions of non-compliance without evidence).

---

# 24. MODULE 9+ DESIGN CONSTRAINTS

**Boundary (audit §20.2, §23):** Module 9 is **NOT implemented** by this Rulebook. No architecture is invented before the Rulebook is reviewed. Candidate requirements below are design inputs.

## 24.1 DESIGN PRECONDITIONS (fact-specific / legal-review; resolve before claiming applicability) — from audit §27.2-A

| # | Precondition | Rule IDs | Class |
|---|---|---|---|
| 1 | PKP status (sell-side e-Faktur issuance) | TAX-PPN-01 | Tax / legal review — UNVERIFIED |
| 2 | PPh applicability (23/21 exact model for the commercial structure) | TAX-PPH-01/02/03 | Tax / legal review |
| 3 | B2G VAT collector status of actual customer base | TAX-PPN-04 | Tax / legal review |
| 4 | DPO / PDP function officer applicability | PDP-DPO-001, ROLE-02 | Legal review (conditional) |
| 5 | TKDN/SNI applicability to AJM's product lines | TKDN-01/02 | Procurement / legal review |
| 6 | PMSE classification (Pedagang vs Retail Online vs platform) | PMSE-01..04 | Legal review (conditional) |

## 24.2 MODULE 9 IMPLEMENTATION REQUIREMENTS (design inputs for Module 9) — from audit §27.2-B

| # | Requirement | Rule IDs | Class |
|---|---|---|---|
| 1 | TaxRule engine: PPN DPP nilai lain, faktur code hierarchy, B2G collection | TAX-PPN-01/02/03/04 | Implementation |
| 2 | PDP request workflow (rights fulfillment) | PDP-RIGHT-001..009, PDP-PROC-001 | Implementation |
| 3 | Statutory timers (withdrawal Ps 40(2), restriction Ps 41(1), breach Ps 46(1)) | PDP-3X24-001/002/003 | Implementation |
| 4 | PSE certificate registry (Sertifikat Elektronik via PSrE Indonesia) | PSE-REG-001, PSE-CERT-001 | Implementation |
| 5 | Audit-trail mapping of Module 8 to PP 71/2019 Ps 22(1) | PSE-AUDIT-001, ROLE-06 | Implementation |
| 6 | Document labelling (mirror vs original; no `ORD-*` as official PO; no non-catalog as e-catalog) | GOV-01, PROH-01, PROH-02, DOC-03 | Implementation |
| 7 | Retention policy engine (tax/PDP/contract/evidence matrix) | DOC-04, PROH-08, §19 | Implementation |
| 8 | Supplier/product compliance records (eligibility & evidence attributes, no auto-%) | GOV-02, TKDN-01/02, PROH-03 | Implementation |

## 24.3 MODULE 9 CANDIDATE CLASSIFICATION

| # | Candidate | Rule IDs | Classification |
|---|---|---|---|
| 1 | Regulatory/PSE Compliance (registration, certificate, audit-trail mapping, incident reporting) | PSE-*, INC-PSE-* | **MUST HAVE** (legal obligation REQUIRED) |
| 2 | PDP/Privacy Fulfillment (rights, consent, 3×24h timers, DPO) | PDP-*, ROLE-02/07/08 | **MUST HAVE** (legal obligation REQUIRED); DPO trigger → **BLOCKED BY LEGAL REVIEW** |
| 3 | Tax Engine (PPN DPP, faktur code, PPh flags, B2G collection) | TAX-* | **MUST HAVE** (PPN); PPh/B2G collector → **BLOCKED BY LEGAL REVIEW** (preconditions) |
| 4 | Supplier & Product Certification (TKDN/SNI, catalog status, eligibility) | TKDN-*, LKPP-05, GOV-02, PMSE-01/02 | **MUST HAVE** (evidence records); applicability → **BLOCKED BY LEGAL REVIEW** |
| 5 | Document & Evidence Governance (labelling, retention, template control) | DOC-*, GOV-01, PROH-01/08 | **MUST HAVE** |
| 6 | Procurement-Channel Intelligence (method abstraction, e-purchasing defaults + warnings, category gap matrix) | GOV-03, LKPP-01/02/03/06 | **SHOULD HAVE** (channel awareness; PPK nuance → human review) |
| 7 | Security Governance (access, encryption, continuity, incident response) | SEC-* | **MUST HAVE** |
| 8 | Regulatory Change Management | §20 | **SHOULD HAVE** |
| — | Coretax direct API integration | TAX-CRT-01 | **OUT OF SCOPE / FUTURE** (optional business decision) |
| — | Certified TTE signing module | B2B-04 | **FUTURE / BLOCKED BY LEGAL REVIEW** (counterparty market requirement) |

**Module 9 candidate architecture** is intentionally NOT specified until this Rulebook is reviewed.

---

# 25. RULEBOOK CHANGE LOG

| Version | Date | Change | Source | Reviewer |
|---|---|---|---|---|
| 1.0 | 14-Aug-2026 | Initial Rulebook derived from approved audit `REGULATORY_COMPLIANCE_AUDIT.md` v1.0 | Audit (approved) | Compliance reviewer (capability ROLE-04) |

**Normalization exceptions — rules that could not be normalized cleanly:**

| Rule ID | Reason | Handling |
|---|---|---|
| TAX-CRT-01 | Audit records applicability as `OPTIONAL` (not one of the five standard status values) | Preserved as `OPTIONAL` in the rule entry and counted separately in the validation report |
| PSE-DATA-003 | Audit records `CONDITIONAL / LEGAL REVIEW` (composite of CONDITIONALLY REQUIRED + legal review) | Preserved verbatim; automation = PROFESSIONAL LEGAL/TAX REVIEW |
| TAX-PPH-03 | Basis unresolved | Preserved as `UNCERTAIN / LEGAL REVIEW`; professional tax review required (§23 #13) |
| GOV-05 / DOC-05 / ROLE-03/04/07/08 / PDP-RIGHT-008 | Low-risk informational | Compacted fields retained; source, applicability, status, provenance preserved |

---

# APPENDIX A — RULE INDEX

98 discrete Rule IDs, zero duplicates (audit Appendix B). Cross-reference integrity: every ID below resolves to exactly one entry in sections §4–§16.

**Government Procurement (§4):** GOV-01, GOV-02, GOV-03, GOV-04, GOV-05
**LKPP / INAPROC / E-Catalog (§5):** LKPP-01, LKPP-02, LKPP-03, LKPP-04, LKPP-05, LKPP-06
**TKDN / SNI (§6):** TKDN-01, TKDN-02, TKDN-03, TKDN-04, TKDN-05
**B2B / Electronic Transactions (§7):** B2B-01, B2B-02, B2B-03, B2B-04, B2B-05
**ETS (§7):** ETS-01, ETS-02, ETS-03
**PSE (§8):** PSE-REG-001, PSE-REG-002, PSE-REG-003, PSE-GOV-001, PSE-GOV-002, PSE-GOV-003, PSE-DATA-001, PSE-DATA-002, PSE-DATA-003, PSE-AUDIT-001, PSE-SEC-001, PSE-CERT-001, PSE-SANC-001
**PMSE (§9):** PMSE-01, PMSE-02, PMSE-03, PMSE-04
**Tax (§10):** TAX-PPN-01, TAX-PPN-02, TAX-PPN-03, TAX-PPN-04, TAX-PPN-05, TAX-PPN-06, TAX-PPH-01, TAX-PPH-02, TAX-PPH-03, TAX-CRT-01
**PDP / Privacy (§11):** PDP-RIGHT-001, PDP-RIGHT-002, PDP-RIGHT-003, PDP-RIGHT-004, PDP-RIGHT-005, PDP-RIGHT-006, PDP-RIGHT-007, PDP-RIGHT-008, PDP-RIGHT-009, PDP-PROC-001, PDP-BREACH-001, PDP-3X24-001, PDP-3X24-002, PDP-3X24-003, PDP-DPO-001, PDP-DPO-002
**Security (§12):** SEC-01, SEC-02, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07
**Documents / Evidence (§13):** DOC-01, DOC-02, DOC-03, DOC-04, DOC-05
**Roles / Authority (§14):** ROLE-01, ROLE-02, ROLE-03, ROLE-04, ROLE-05, ROLE-06, ROLE-07, ROLE-08
**Incident (§15):** INC-PSE-001, INC-PSE-002, INC-PDP-001
**Prohibited Behaviors (§16):** PROH-01, PROH-02, PROH-03, PROH-04, PROH-05, PROH-06, PROH-07, PROH-08

**Count check:** 5 + 6 + 5 + 5 + 3 + 13 + 4 + 10 + 16 + 7 + 5 + 8 + 3 + 8 = **98**.

---

# APPENDIX B — REGULATION INDEX

From audit §4 (Regulation Inventory) and §28 (References). Official primary sources only.

| Regulation | Issuer | Effective | Status @ 13-Aug-2026 | Official source |
|---|---|---|---|---|
| UU 11/2008 jo UU 1/2024 (ITE) | DPR/RI | 2008 / 2024 | In force | jdih.komdigi.go.id (`view/id/167`) |
| UU 27/2022 (PDP) | DPR/RI | 17-Oct-2022 (transition per implementing reg) | In force; implementing reg pending | jdih.komdigi.go.id |
| PP 71/2019 (PSTE) | GoI | 2019 | In force | jdih.komdigi.go.id (`view/id/695`) |
| PP 80/2019 (PMSE) | GoI | 2019 | In force | jdih.setkab.go.id |
| Perpres 16/2018 jo 12/2021 jo 46/2025 (Pengadaan Barang/Jasa Pemerintah) | Presiden | 2018 / 2021 / 2025 | In force | jdih.lkpp.go.id |
| Perpres 17/2023 (Percepatan Transformasi Digital Pengadaan — basis hukum INAPROC) | Presiden | 2023 | In force | jdih.lkpp.go.id |
| Kepka LKPP 122/2022 (e-Purchasing) | LKPP | 2022 | In force | jdih.lkpp.go.id |
| Kepka LKPP 177/2024 (e-Katalog adjustments) | LKPP | 2024 | In force | jdih.lkpp.go.id |
| Catalog V6 Terms & Conditions | LKPP | current | In force | catalog.lkpp.go.id |
| Permenkominfo 5/2020 jo 10/2021 (PSE registration) | Kominfo/Komdigi | 2020 / 2021 | In force | jdih.komdigi.go.id |
| Permenkominfo 11/2022 (Tata Kelola Penyelenggaraan Sertifikasi Elektronik) | Kominfo/Komdigi | 5-Oct-2022 | In force | jdih.komdigi.go.id (`view/id/833`) |
| PMK 11/2025 jo PMK 53/2025 (PPN rate & DPP nilai lain) | Menkeu | 2025 | In force | jdih.kemenkeu.go.id |
| PMK 131/2024 (PPN treatment: Impor/Penyerahan BKP & JKP, Pemanfaatan BKP Tidak Berwujud & JKP dari Luar Daerah Pabean) | Menkeu | 2024 | In force | jdih.kemenkeu.go.id |
| PMK 81/2024 (SIAP/Coretax) jo PMK 54/2025 (Perubahan Ketiga) jo PMK 1/2026 (Perubahan Keempat) | Menkeu | 2024 / 2025 / 2026 | In force (as amended) | jdih.kemenkeu.go.id |
| PER-1/PJ/2025 (Petunjuk Teknis Pembuatan Faktur Pajak dalam pelaksanaan PMK 131/2024) | DJP | 2025 | In force | jdih.kemenkeu.go.id |
| PER-11/PJ/2025 (Pelaporan PPh, PPN, PPnBM & Bea Meterai dalam SIAP; Lampiran D = kode & nomor seri faktur pajak) | DJP | 2025 | In force | jdih.kemenkeu.go.id |
| PMK 59/2022 (pemungutan PPN instansi pemerintah) | Menkeu | 2022 | In force | jdih.kemenkeu.go.id |
| Permendag 31/2023 (perizinan berusaha PMSE) | Mendag | 2023 | In force | jdih.kemendag.go.id |
| Peraturan BSSN (BSrE Ops) | BSSN | current | In force | jdih.bssn.go.id |
| ~~Peraturan LKPP 21/2018~~ (Jadwal Retensi Arsip LKPP) | LKPP | 2018 | **Revoked by Peraturan LKPP 6/2023** — historical only | jdih.lkpp.go.id |

---

# APPENDIX C — UNRESOLVED QUESTIONS

All UNCERTAIN / LEGAL-REVIEW items preserved from the audit. None resolved by guesswork.

1. **TAX-PPH-03** legal basis — `REQUIRES PROFESSIONAL TAX REVIEW` (§10, §23 #13).
2. **PSE-DATA-003** strategic-data classification — fact-specific (§8).
3. **PMSE-04** Retail Online applicability — classification unresolved (§9, §23 #1).
4. **PDP-DPO-001 / ROLE-02** DPO trigger — conditional on processing scale/type (§11, §14).
5. **TKDN-01/02/03/04** applicability to AJM product lines — fact-specific (§6, §23 #3).
6. **TAX-PPH-01/02** exact PPh model — fact-specific (§10, §23 #4).
7. **TAX-PPN-04** B2G collector status of actual customer base — customer-specific (§10, §23 #10).
8. **PSE-CERT-001** implementation path (PSrE selection, cost, ops) — `REQUIRES FURTHER VERIFICATION` (§8, §23 #9).
9. **B2B-03** six-criteria assessment for AJM's flows — `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` (§7, §23 #12).
10. **B2B-04** certified TTE requirement by counterparties — market-specific (§7, §23 #7).
11. **Retention** for RFQ/order/PO and BAST — `UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE` (§19).
12. **PDP implementing regulation** (UU 27/2022 transition) — `UNCERTAIN / LEGAL REVIEW` (§11, §23 #2).
13. **Coretax integration approach** — optional business decision (§10, §23 #6).
14. **Cross-border counterparty jurisdiction** — beyond Indonesian scope (§23 #8).
15. **Real-world status of AJM licenses/certificates/registrations** — `UNVERIFIED`, requires AJM evidence (§23 #11).
16. **PMK 81/2024 Perubahan Pertama/Kedua** numbers — not individually verified; verified chain = PMK 54/2025 (Perubahan Ketiga), PMK 1/2026 (Perubahan Keempat) (§23 #14).

---

*End of Regulatory Rulebook v1.0. Derived exclusively from `docs/REGULATORY_COMPLIANCE_AUDIT.md`. No new legal obligations invented. All uncertainties preserved. Module 9 not implemented.*
