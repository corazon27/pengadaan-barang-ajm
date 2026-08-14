# REGULATORY COMPLIANCE AUDIT — AJM Procurement & Commerce Platform

**Project:** CV Abadi Jaya Mitra — B2B / B2G Supplier-Side Procurement Platform
**Repository:** `D:\laravel\pengadaan-barang-ajm`
**Audit type:** Specification-readiness & regulatory applicability audit (NOT a legal-compliance certification)
**Research cutoff:** 13-Aug-2026 (all legal citations current as of this date; verification date recorded per rule)
**Audit version:** 1.0 (consolidation of Clusters A–E)
**Status:** FINAL — pending professional legal/tax review for items marked `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW`

---

## 0. How to Read This Document

Every material requirement carries three independent status columns that must not be conflated:

| Column | Meaning |
|---|---|
| **Regulatory Applicability** | What the law requires of AJM as a business/PSE. Values: `REQUIRED`, `CONDITIONALLY REQUIRED`, `RECOMMENDED`, `NOT APPLICABLE`, `UNCERTAIN / LEGAL REVIEW`. |
| **System Readiness** | What the current platform (`Module 1–8`) implements. Values: `IMPLEMENTED`, `PARTIAL`, `MISSING`, `NOT APPLICABLE`, `UNVERIFIED`. |
| **Real-World Compliance** | AJM's actual operational/legal status. Values: `COMPLIANT` (evidence exists), `NON-COMPLIANT` (evidence exists), `UNVERIFIED`, `NOT APPLICABLE`. |

**Rule:** A `MISSING` platform feature does NOT automatically mean legal non-compliance. Legal non-compliance may only be asserted where real-world evidence demonstrates it. Absent such evidence, use `UNVERIFIED`.

Evidence fields preserved per rule: regulation, article/paragraph, official source, effective status, verification date, applicability, confidence, unresolved uncertainty.

---

## 1. Executive Summary

AJM (CV Abadi Jaya Mitra) operates a supplier-side B2B/B2G procurement platform (Modules 1–8 implemented: RFQ, Orders, Payments/TOP, Invoice/BAST documents, Notifications, Audit Logs, Analytics). The platform positions AJM as a **supplier supporting vendor**, not as an official procurement system.

**Headline conclusions:**

1. **Five regulatory clusters** (A–E) were researched against primary Indonesian sources (JDIH LKPP, JDIH Kemenkeu, JDIH Komdigi/Kominfo, JDIH Kemendag, JDIH Setkab, BSSN). All 5 clusters are complete for the current research scope.
2. **Specification-readiness verdict: `YES WITH CONDITIONS`** (see §26). The platform is well specified for Modules 1–8 and ready to begin Module 9 design, provided the top-10 gaps in §26.3 are incorporated as design inputs.
3. **Highest-priority mandatory obligations** (regulatory applicability REQUIRED, all currently MISSING or PARTIAL in platform):
   - PSE registration & electronic certificate (PP 71/2019 Ps 6(1), Ps 51(1); Permenkominfo 5/2020 jo 10/2021; Permenkominfo 11/2022).
   - PPN compliance for B2B (faktur code hierarchy PER-11/PJ/2025; PMK 59/2022 for B2G; DPP nilai lain PMK 11/2025 jo 53/2025).
   - PDP rights fulfillment incl. 3×24-hour deadlines (UU 27/2022 Ps 40(2), 41(1), 46(1)).
   - Audit trail (PP 71/2019 Ps 22(1)) — PARTIAL (Module 8 exists but must be mapped to PSE obligations).
4. **Deliberately preserved uncertainties** (not resolved by guesswork): TKDN/SNI supplier-evidence requirements, PPh 23 treatment for the exact commercial model, Coretax integration approach, PDP implementing regulation status, and PMSE registration interface. All marked `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW`.
5. **Automation candidates** (§24–25) show a clear, low-risk path: most rules map to deterministic automation (faktur code selection, tax DPP calc, 3×24h timers, document checklists), a smaller set requires human review, and a few require legal review before automation.

---

## 2. Scope & Assumptions

### 2.1 In scope
- Regulatory applicability of Indonesian laws/regulations to AJM's B2B/B2G procurement platform, Modules 1–8 as currently specified.
- Specification-readiness for Module 9 design (NOT a legal compliance certification).
- Procurement law, electronic transactions/PSE/PMSE, tax, PDP, security governance, documents/evidence, roles, prohibitions.

### 2.2 Out of scope
- Legal opinion or certification of compliance.
- Real-world verification of AJM's operational status (licenses, certificates, registrations).
- Laws of jurisdictions other than Indonesia (cross-border flag noted in §8.6).
- Site-specific tax advice; per-transaction calculations require professional tax review.

### 2.3 Assumptions
- AJM is an Indonesian legal entity (CV) operating from Indonesia.
- AJM conducts electronic transactions (business-to-business) and participates in government procurement as a **supplier** (seller of goods/services) supporting the vendor/procurement process — it does NOT operate an official government procurement information system.
- "Government procurement" in this audit = AJM's role as a registered/eligible supplier bidding for government contracts (TKDN/SNI evidence, e-catalog participation), not the operation of a state procurement system.
- Modules 1–8 status is taken from `docs/MODULES_PROGRESS.md` and the codebase as of the audit date.
- Research cutoff 13-Aug-2026. Where a regulation is under transition (e.g., PDP implementing regulation, Coretax), this is recorded as `UNCERTAIN / LEGAL REVIEW` rather than resolved.

### 2.4 Verification date
All citations were checked against official sources on or before **13-Aug-2026**. Each rule matrix records the specific verification date.

---

## 3. AJM Business Model Classification

| Attribute | Value | Basis |
|---|---|---|
| Legal entity | CV (Persekutuan Komanditer / limited partnership) | Cluster A |
| Economic role | Supplier (penyedia barang/jasa) in B2B and B2G transactions | Cluster A |
| Platform role | Supplier-side support portal; internal mirror of procurement documents | Cluster A decision (approved) |
| PSE classification | **PSE lingkup privat** (Penyelenggara Sistem Elektronik privat) | PP 71/2019 Ps 2(2); Cluster C |
| PMSE classification | Pedagang/Penyedia (supplier) via Sistem Elektronik — merchant/seller; NOT "Retail Online" (conditional; subject to Permendag 31/2023 definitions) | Cluster C |
| Tax status | PKP (Pengusaha Kena Pajak) on sell-side — **UNVERIFIED/assumed** (required for e-Faktur issuance; subject to AJM DJP registration evidence) | Cluster D |
| Data subject types | Supplier contacts, customer business contacts, employees | Cluster E |
| Data processed | Company + personal data (contacts, KTP/NPWP/NIB of PICs, financial) | Cluster E |

### 3.1 Why classification matters
Each classification triggers a distinct regulatory stack:
- PSE privat → PP 71/2019 + Permenkominfo 5/2020 jo 10/2021 (registration) + Permenkominfo 11/2022 (certificates).
- PMSE supplier → Permendag 31/2023 obligations + tax-in-transaction obligations.
- B2G supplier → Perpres 16/2018 jo 12/2021 jo 46/2025 + Kepka LKPP + TKDN/SNI.
- PDP controller → UU 27/2022 rights, 3×24h deadlines, DPO appointment.

---

## 4. Regulation Inventory

| # | Regulation | Issuer | Effective | Status @ 13-Aug-2026 | Cluster | Official source |
|---|---|---|---|---|---|---|
| 1 | UU 11/2008 jo UU 1/2024 (ITE) | DPR/RI | 2008 / 2024 | In force | C | jdih.komdigi.go.id |
| 2 | UU 27/2022 (PDP) | DPR/RI | 17-Oct-2022 (transition per implementing reg) | In force; implementing reg pending | E | jdih.komdigi.go.id |
| 3 | PP 71/2019 (PSTE) | GoI | 2019 | In force | C, E | jdih.komdigi.go.id |
| 4 | PP 80/2019 (PMSE) | GoI | 2019 | In force | C | jdih.setkab.go.id |
| 5 | Perpres 16/2018 jo 12/2021 jo 46/2025 (Pengadaan Barang/Jasa Pemerintah) | Presiden | 2018 / 2021 / 2025 | In force | A, B | jdih.lkpp.go.id |
| 6 | Perpres 17/2023 (Percepatan Transformasi Digital Pengadaan — basis hukum INAPROC) | Presiden | 2023 | In force | B | jdih.lkpp.go.id |
| 7 | Kepka LKPP 122/2022 (e-Purchasing) | LKPP | 2022 | In force | B | jdih.lkpp.go.id |
| 8 | Kepka LKPP 177/2024 (e-Katalog adjustments) | LKPP | 2024 | In force | B | jdih.lkpp.go.id |
| 9 | Catalog V6 Terms & Conditions | LKPP | current | In force | B | Catalog V6 official T&C |
| 10 | Permenkominfo 5/2020 jo 10/2021 (PSE registration) | Kominfo/Komdigi | 2020 / 2021 | In force | C | jdih.komdigi.go.id |
| 11 | Permenkominfo 11/2022 (Tata Kelola Penyelenggaraan Sertifikasi Elektronik) | Kominfo/Komdigi | 5-Oct-2022 | In force | C | jdih.komdigi.go.id (`view/id/833`) |
| 12 | PMK 11/2025 jo PMK 53/2025 (PPN rate & DPP nilai lain) | Menkeu | 2025 | In force | D | jdih.kemenkeu.go.id |
| 13 | PMK 131/2024 (PPN treatment: Impor/Penyerahan BKP & JKP, Pemanfaatan BKP Tidak Berwujud & JKP dari Luar Daerah Pabean) | Menkeu | 2024 | In force | D | jdih.kemenkeu.go.id |
| 14 | PMK 81/2024 (Ketentuan Perpajakan dalam Rangka Pelaksanaan SIAP / Coretax) jo PMK 54/2025 (Perubahan Ketiga) jo PMK 1/2026 (Perubahan Keempat) | Menkeu | 2024 / 2025 / 2026 | In force (as amended) | D | jdih.kemenkeu.go.id |
| 15 | PER-1/PJ/2025 (Petunjuk Teknis Pembuatan Faktur Pajak dalam pelaksanaan PMK 131/2024) | DJP | 2025 | In force | D | jdih.kemenkeu.go.id |
| 16 | PER-11/PJ/2025 (Pelaporan PPh, PPN, PPnBM & Bea Meterai dalam SIAP; Lampiran D = kode & nomor seri faktur pajak) | DJP | 2025 | In force | D | jdih.kemenkeu.go.id |
| 17 | PMK 59/2022 (pemungutan PPN instansi pemerintah) | Menkeu | 2022 | In force | D | jdih.kemenkeu.go.id |
| 18 | Permendag 31/2023 (perizinan berusaha PMSE) | Mendag | 2023 | In force | C | jdih.kemendag.go.id |
| 19 | Peraturan BSSN (BSrE Ops) | BSSN | current | In force | C | jdih.bssn.go.id |

**Cross-cluster rule count:** 98 discrete Rule IDs (see Appendix B). None duplicated.

---

## 5. Government Procurement (Cluster A)

### 5.1 Core positioning
AJM is a **supplier** participating in government procurement. The platform is a supplier-side support tool and internal document mirror. AJM does not operate an official procurement system (SIKAP/e-Procurement domain belongs to LKPP/KPB).

### 5.2 Principle decisions (approved in review)
- **Principles A–D frozen** — research all procurement methods; do not assume a single method.
- **Do NOT blanket-prohibit** any legitimate procurement method; support multiple channels.
- `ORD-*` / internal order documents must **never be presented as an official PO**; disclaimers and clear labelling required.
- Internal mirror records of official documents are lawful as internal business records provided they are not presented as originals.

### 5.3 Rules

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| GOV-01 | Clear labelling: platform documents are internal mirrors, not official procurement instruments | Perpres 16/2018 jo 46/2025 (general) | REQUIRED | PARTIAL | UNVERIFIED | Document engine labels needed |
| GOV-02 | Supplier eligibility tracking (NIB, NPWP, NIB, KBLI) | Perpres 16/2018 jo 46/2025 Ps 4(2) | REQUIRED | MISSING | UNVERIFIED | Module 9 candidate |
| GOV-03 | Support for multiple procurement methods (tender, direct procurement, e-purchasing, selection) | Perpres 16/2018 jo 46/2025 | REQUIRED | PARTIAL | UNVERIFIED | Abstraction layer needed |
| GOV-04 | Do not present internal order status as official award status | Perpres 16/2018 | REQUIRED | PARTIAL | UNVERIFIED | Compliance label |
| GOV-05 | No liability for official procurement outcomes (system boundary) | General | RECOMMENDED | PARTIAL | UNVERIFIED | EULA/ToS |

**Uncertainties preserved:** `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` for the exact interaction between platform-generated documents and formal tender documentation requirements.

---

## 6. LKPP / INAPROC / E-Catalog (Cluster B)

### 6.1 Regulatory frame
- **Perpres 16/2018 jo 12/2021 jo 46/2025** — the umbrella government procurement regulation.
- **Perpres 17/2023** — INAPROC (Integrated National Procurement; Percepatan Transformasi Digital Pengadaan, dikembangkan LKPP + PT Telkom).
- Note: "Kepka LKPP 21/2018" is the **Jadwal Retensi Arsip LKPP** (archival-retention schedule), NOT INAPROC; revoked by **Peraturan LKPP 6/2023** (pencabutan peraturan di bidang kearsipan).
- **Kepka LKPP 122/2022** — e-Purchasing (e-purchasing default when goods are in e-Catalog).
- **Kepka LKPP 177/2024** — e-Katalog adjustments.
- **Catalog V6 T&C** — platform terms for e-catalog participants.

### 6.2 Rules

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| LKPP-01 | E-purchasing is the **default** channel when goods are cataloged, subject to statutory exceptions and PPK assessment | Kepka LKPP 122/2022 | CONDITIONALLY REQUIRED | PARTIAL | UNVERIFIED | Platform must warn not auto-select |
| LKPP-02 | Categorical gap matrix over % coverage — track which product categories are cataloged vs not | Kepka 122/2022, 177/2024 | REQUIRED | MISSING | UNVERIFIED | Module 9 |
| LKPP-03 | INAPROC channel awareness (procurement method per KPB) | Perpres 17/2023 | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | INAPROC basis = Perpres 17/2023, not Kepka 21/2018 |
| LKPP-04 | Catalog T&C awareness for participating suppliers | Catalog V6 T&C | REQUIRED | MISSING | UNVERIFIED | ToS + checklist |
| LKPP-05 | E-catalog listing status tracking for own products | Catalog V6 T&C | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | |
| LKPP-06 | Do not present non-catalog offers as e-catalog offers | Kepka 122/2022 | REQUIRED | MISSING | UNVERIFIED | Integrity control |

**Uncertainties preserved:** `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` — whether any particular product line is inside/outside Catalog coverage at any given time; whether e-purchasing applies to the specific KPB. This is fact-specific and time-sensitive, not a static rule.

---

## 7. TKDN / SNI (Cluster B)

### 7.1 Regulatory frame
TKDN (Tingkat Komponen Dalam Negeri) and SNI (Standar Nasional Indonesia) requirements are attached to **products/services offered to government**, per the procurement regulations and LKPP catalog rules. Evidence-based, not percentage-based in the platform.

### 7.2 Rules

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| TKDN-01 | Capture & store TKDN certificate/evidence per product | Perpres 16/2018 jo 46/2025; Kepka 122/2022 | CONDITIONALLY REQUIRED (only for cataloged/offered products) | MISSING | UNVERIFIED | Evidence attribute, not % coverage |
| TKDN-02 | Capture & store SNI certificate/evidence per product | Same | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | |
| TKDN-03 | Validate evidence currency/expiry | General | RECOMMENDED | MISSING | UNVERIFIED | Module 9 |
| TKDN-04 | Warning when non-certified product offered to government | Kepka 122/2022 | RECOMMENDED | MISSING | UNVERIFIED | Procurement-channel warning |
| TKDN-05 | Do not infer % TKDN automatically | — | NOT APPLICABLE (no statutory auto-calc basis) | MISSING | — | Requires certificate data |

**Uncertainties preserved:** `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` — whether specific products in AJM's catalog require mandatory SNI/TKDN; the certification validity rules are fact-specific. No %-coverage claim is asserted.

---

## 8. B2B Legal Analysis (Cluster A/C)

### 8.1 Nature of B2B electronic transactions
B2B contracts formed via the platform are **electronic transactions** under UU ITE 11/2008 jo 1/2024. Electronic records and electronic signatures are admissible evidence (UU ITE Ps 5–6, 11–12; PP 71/2019 Ps 47–49).

### 8.2 Contract formation
| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| B2B-01 | Capture consent/acceptance events for each transaction | UU ITE 11/2008 jo 1/2024 Ps 5–6, 18–19; PP 71/2019 Ps 47 | REQUIRED | PARTIAL | UNVERIFIED | Audit log timestamps; strengthen |
| B2B-02 | Store electronic records with integrity & retrievability | UU ITE Ps 5; PP 71/2019 Ps 22(1) | REQUIRED | PARTIAL | UNVERIFIED | Module 8 audit log exists |
| B2B-03 | Non-certified electronic signature validity (6-criteria rule) | UU ITE Ps 11; PP 71/2019 Ps 52–53 | CONDITIONALLY REQUIRED | PARTIAL | UNVERIFIED | Cautious wording — NOT a boolean rule |
| B2B-04 | Support certified TTE when requested by counterparties | UU ITE Ps 11(2); PP 71/2019 Ps 53 | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | PSrE-certified signing module |
| B2B-05 | Terms & conditions of platform use (ToS) binding | UU ITE Ps 18 | REQUIRED | PARTIAL | UNVERIFIED | |

### 8.3 Electronic signature — careful wording
The platform may use non-certified electronic signatures. Their validity is **conditional** on the six criteria in UU ITE Ps 11 (reliability of the method, link to signer, etc.) — not automatically invalid. Do not implement a boolean "certified vs not" rule.

### 8.4 B2G nuance
For government contracts, signature/evidence standards may be set by the applicable KPB/LKPP rules; the platform should **mirror** official execution rather than substitute for it.

### 8.5 Unresolved
`REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` — whether AJM's counterparties will require certified TTE; whether specific government buyers require BSrE-based signatures.

### 8.6 Cross-border
If foreign counterparties participate, flag as `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` (jurisdictional questions beyond Indonesian law scope of this audit).

---

## 9. Electronic Transaction / Contract / Signature (Cluster C)

Consolidated in §8 (contract formation) and §10 (PSE). Additional rules:

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| ETS-01 | Timestamp integrity on all transaction records | PP 71/2019 Ps 47, 49 | REQUIRED | PARTIAL | UNVERIFIED | |
| ETS-02 | Electronic records admissible & preserved | UU ITE Ps 5; PP 71/2019 Ps 22(1) | REQUIRED | PARTIAL | UNVERIFIED | Audit trail |
| ETS-03 | Consent capture per electronic transaction | UU ITE Ps 18–19 | REQUIRED | PARTIAL | UNVERIFIED | |

---

## 10. PSE (Cluster C/E)

### 10.1 Classification
AJM is a **PSE lingkup privat** (PP 71/2019 Ps 2(2)) conducting **electronic transactions**. Applies: registration, electronic certificate, audit trail, reliability/security, incident reporting, sanctions exposure.

### 10.2 Rules

| Rule ID | Requirement | Regulation | Article | Reg. Applicability | System Readiness | Real-World | Confidence |
|---|---|---|---|---|---|---|---|
| PSE-REG-001 | PSE registration before system used by users | PP 71/2019 | Ps 6(1) | REQUIRED | MISSING | UNVERIFIED | CONFIRMED |
| PSE-REG-002 | PSE privat registration interface (PMSE/electronic services) | Permenkominfo 5/2020 jo 10/2021 | Ps 4–6 | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | CONFIRMED |
| PSE-REG-003 | Registration data accuracy & maintenance | Permenkominfo 5/2020 jo 10/2021 | Ps 7 | REQUIRED | MISSING | UNVERIFIED | CONFIRMED |
| PSE-GOV-001 | System reliability & security obligations | PP 71/2019 | Ps 11, 12, 13, 14 | REQUIRED | PARTIAL | UNVERIFIED | CONFIRMED |
| PSE-GOV-002 | Continuity & disaster recovery | PP 71/2019 | Ps 19 | REQUIRED | PARTIAL | UNVERIFIED | CONFIRMED (Ps 19 = tata kelola & keberlangsungan; Ps 21(1) does NOT mandate DR — DR is engineering best practice) |
| PSE-GOV-003 | User assistance/information | PP 71/2019 | Ps 13, 14 | REQUIRED | PARTIAL | UNVERIFIED | CONFIRMED |
| PSE-DATA-001 | Data protection in PSE (PDP principles) | UU 27/2022 | Ps 16 | REQUIRED | PARTIAL | UNVERIFIED | CONFIRMED |
| PSE-DATA-002 | Data localization — overseas transfer permitted with safeguards | PP 71/2019 | Ps 21(1) | CONDITIONALLY REQUIRED | UNVERIFIED | UNVERIFIED | CONFIRMED (not a blanket Indonesia-only rule) |
| PSE-DATA-003 | Protection of strategic/personal data | PP 71/2019 | Ps 14 | CONDITIONALLY REQUIRED | UNVERIFIED | UNVERIFIED | CONFIRMED |
| PSE-AUDIT-001 | Audit trail (log) of system operations | PP 71/2019 | **Ps 22(1)** | REQUIRED | PARTIAL (Module 8) | UNVERIFIED | CONFIRMED — map Module 8 audit log to PSE audit-trail obligation |
| PSE-SEC-001 | Security assurance / reliability (incl. uji kelayakan) | PP 71/2019 | Ps 11, 12 | CONDITIONALLY REQUIRED | PARTIAL | UNVERIFIED | CONFIRMED |
| PSE-CERT-001 | PSE must hold Sertifikat Elektronik issued by PSrE Indonesia | PP 71/2019 Ps 51(1); Permenkominfo 11/2022 Ps 24(2), 25 | Ps 51(1); PM 11/2022 Ps 24(2) | **REQUIRED** | **MISSING** | **UNVERIFIED** | CONFIRMED (legal); SYSTEM/REAL-WORLD separate |
| PSE-SANC-001 | Sanctions exposure for PSE violations | PP 71/2019 | Ps 100 | REQUIRED (awareness) | N/A | UNVERIFIED | CONFIRMED |
| INC-PSE-001 | Serious system disruption reporting | PP 71/2019 | **Ps 24(3)** | REQUIRED | MISSING | UNVERIFIED | CONFIRMED |
| INC-PSE-002 | PDP failure in PSE — separate incident class | PP 71/2019 | Ps 14(5) | REQUIRED | MISSING | UNVERIFIED | CONFIRMED |

### 10.3 PSE-CERT-001 — detailed primary-source record (preserved evidence)

| Field | Value |
|---|---|
| Regulation | PP 71/2019 (PSTE) |
| Article/paragraph | **Ps 51 ayat (1)** and (3); implementing **Permenkominfo 11/2022 Ps 24(2), Ps 25** |
| Exact text (Ps 51(1)) | "Penyelenggara Sistem Elektronik sebagaimana dimaksud dalam Pasal 2 ayat (2) **wajib memiliki Sertifikat Elektronik**." |
| Exact text (Ps 51(3)) | "Untuk memiliki Sertifikat Elektronik, Penyelenggara Sistem Elektronik dan Pengguna Sistem Elektronik **harus mengajukan permohonan kepada Penyelenggara Sertifikasi Elektronik Indonesia**." |
| Implementing (Permenkominfo 11/2022 Ps 24(2)) | "Penyelenggara Sistem Elektronik **wajib memiliki Sertifikat Elektronik yang diterbitkan oleh PSrE Indonesia**." |
| Lifecycle | Ps 25–26, 29(2): issuance, verification, renewal (perpanjangan), blocking (pemblokiran), revocation (pencabutan). |
| Issuer | **PSrE Indonesia** (Penyelenggara Sertifikasi Elektronik Indonesia) — badan hukum Indonesia, berdomisili di Indonesia, recognized by Menteri, berinduk kepada PSrE Induk (Komdigi). For AJM: PSrE non-Instansi. |
| Official source | jdih.komdigi.go.id (`view/id/695` for PP 71/2019; `view/id/833` for Permenkominfo 11/2022) |
| Effective status | In force (both) as of 13-Aug-2026 |
| Verification date | 13-Aug-2026 |
| Applicability | REQUIRED for PSE privat (AJM) |
| System Readiness | MISSING (no certificate feature/flag in platform) |
| Real-world compliance | **UNVERIFIED** — no real-world evidence that AJM does or does not hold the certificate |
| Confidence | CONFIRMED (legal obligation); IMPLEMENTATION DETAILS → `REQUIRES FURTHER VERIFICATION` |

**Distinct concepts (must not be conflated):**

| Concept | Basis | Distinct from PSE-CERT |
|---|---|---|
| User TTE (individual signature) | UU ITE Ps 11; PP 71/2019 Ps 52–53 | Yes — different holder/object |
| Certified TTE | PP 71/2019 Ps 53 | Yes — TTE + certification |
| BSrE (BSSN Balai Sertifikasi Elektronik) | BSSN regs | Yes — public-sector CA, not AJM's issuer |
| PSrE (Penyelenggara Sertifikasi Elektronik) | PP 71/2019; PM 11/2022 | PSrE = the CA; AJM is a **holder**, not a PSrE |
| Sertifikat Keandalan / LSK | PP 71/2019 Ps 42–43 | Yes — different document (reliability trust mark), different issuer (LSK) |
| LS PSrE | PM 11/2022 | Yes — assesses PSrE readiness; NOT certificate issuer, NOT LSK |

---

## 11. PMSE (Cluster C)

### 11.1 Classification
AJM sells via electronic systems (PMSE). Under Permendag 31/2023, AJM is classified as **Pedagang (supplier/seller)**, not "Retail Online" — **conditional** on the exact product/service and channel definitions. This classification must be validated by professional review against current Permendag definitions.

### 11.2 Rules

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| PMSE-01 | Supplier identity & business license visibility | Permendag 31/2023 | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | |
| PMSE-02 | Compliance with PMSE business licensing (NIB-based) | Permendag 31/2023 | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | |
| PMSE-03 | Transaction data integrity | PP 80/2019 | REQUIRED | PARTIAL | UNVERIFIED | |
| PMSE-04 | Do NOT apply "Retail Online" obligations without validation | Permendag 31/2023 | NOT APPLICABLE (conditional) | N/A | N/A | Classification uncertain |

**Uncertainties preserved:** `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` — exact AJM classification under Permendag 31/2023 (Pedagang vs Retail Online vs PMSE platform); 99.9% uptime, Indonesia-only hosting, and annual-report obligations are NOT universal and were **removed as universal rules**.

---

## 12. Tax / DJP (Cluster D)

### 12.1 PPN (sell-side, B2B)

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| TAX-PPN-01 | PPN rate & DPP calculation | PMK 11/2025 jo PMK 53/2025 | REQUIRED | PARTIAL | UNVERIFIED | **PPN is NOT simply 12%** — DPP nilai lain 11/12; effective ~11% for standard goods |
| TAX-PPN-02 | Faktur pajak code selection (hierarchy) | PER-11/PJ/2025 Lampiran D | REQUIRED | MISSING | UNVERIFIED | code 02 = only govt VAT collectors; code 03 = only designated collectors; **code 01 = default** |
| TAX-PPN-03 | E-Faktur issuance & reporting | PER-1/PJ/2025 (Petunjuk Teknis), PER-11/PJ/2025 (SIAP reporting), PMK 81/2024 (SIAP umbrella) | REQUIRED | MISSING | UNVERIFIED | PMK 131/2024 is PPN *treatment*, not E-Faktur procedure |
| TAX-PPN-04 | B2G VAT collection (instansi pemerintah) | PMK 59/2022 | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | Applies where customer is a VAT-collecting government institution |
| TAX-PPN-05 | NPWP/NIK identity on counterparties | PER-11/PJ/2025 | REQUIRED | MISSING | UNVERIFIED | PER-1/PJ/2025 is faktur *pembuatan* teknis; identitas pembeli fields live in PER-11/PJ/2025 |
| TAX-PPN-06 | No "SPT Tahunan PPN" concept | — | NOT APPLICABLE (no such filing) | N/A | N/A | Removed as an incorrect rule |

### 12.2 PPh (sell-side / buy-side)

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| TAX-PPH-01 | PPh 23 withholding tracking (sell & buy side separation) | UU PPh; PP implementing | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | `REQUIRES PROFESSIONAL TAX REVIEW` for exact model |
| TAX-PPH-02 | PPh 21 for employees | UU PPh | CONDITIONALLY REQUIRED | MISSING | UNVERIFIED | HR scope |
| TAX-PPH-03 | Tax certificate/NPWP evidence for counterparties | UNCERTAIN — not PMK 131/2024 (that is PPN treatment) | REQUIRED | MISSING | UNVERIFIED | `REQUIRES PROFESSIONAL TAX REVIEW` |

### 12.3 Coretax

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| TAX-CRT-01 | Coretax integration | DJP Coretax | **OPTIONAL** | MISSING | UNVERIFIED | Not legally mandatory for the platform; integration is a business decision |

### 12.4 Key D corrections (accepted in review)
- PPN **not** uniformly 12%; DPP nilai lain per PMK 11/2025 jo 53/2025 → ~11% effective for standard goods.
- Faktur code = **hierarchy**, not a simple picklist of equivalent codes.
- Coretax API = optional.
- No "SPT Tahunan PPN".
- Sell-side vs buy-side PPh separated.
- **Citation corrections (QC pass, 14-Aug-2026):** PMK 131/2024 = PPN **treatment** (not E-Faktur); PMK 81/2024 = SIAP/Coretax umbrella (jo PMK 54/2025 Perubahan Ketiga, PMK 1/2026 Perubahan Keempat); PER-1/PJ/2025 = Petunjuk Teknis Pembuatan Faktur Pajak (not NPWP/NIK identity); PER-11/PJ/2025 = SIAP reporting incl. faktur-pajak code/NSFP (Lampiran D) — source for faktur issuance (TAX-PPN-03) and counterparty NPWP/NIK identity (TAX-PPN-05); TAX-PPH-03 basis `REQUIRES PROFESSIONAL TAX REVIEW` (PMK 131/2024 removed as incorrect citation).

### 12.5 TaxRule engine concept (Module 9 candidate)
A deterministic `TaxRule` engine is proposed (§24) — rule-driven, versioned, no hardcoded rates. **Rates must not be hardcoded**; each rule carries validity dates and official source.

---

## 13. PDP / Privacy (Cluster E)

### 13.1 Data Subject Rights (UU 27/2022 Ps 5–13) — 9 rights

| Rule ID | Right | Base Article | Operational Deadline | System Readiness | Real-World |
|---|---|---|---|---|---|
| PDP-RIGHT-001 | Informasi (Information) | Ps 5 | Reasonable time | MISSING | UNVERIFIED |
| PDP-RIGHT-002 | Koreksi & Pembaruan (Rectification) | Ps 6 | Reasonable time | MISSING | UNVERIFIED |
| PDP-RIGHT-003 | Akses & Salinan (Access & Copy) | Ps 7 | Reasonable time | MISSING | UNVERIFIED |
| PDP-RIGHT-004 | Penghapusan/Pemusnahan (Erasure) | Ps 8 | Reasonable time | MISSING | UNVERIFIED |
| PDP-RIGHT-005 | Tarik Persetujuan (Withdraw Consent) | Ps 9 | **3×24 hours — Ps 40(2)** | MISSING | UNVERIFIED |
| PDP-RIGHT-006 | Keberatan Otomatis (Object to Automated Decision) | Ps 10 | Reasonable time | MISSING | UNVERIFIED |
| PDP-RIGHT-007 | Penundaan/Pembatasan (Restriction/Suspension) | Ps 11 | **3×24 hours — Ps 41(1)** | MISSING | UNVERIFIED |
| PDP-RIGHT-008 | Ganti Rugi (Compensation) | Ps 12 | Civil procedure | N/A | UNVERIFIED |
| PDP-RIGHT-009 | Portabilitas (Portability) | Ps 13 | Reasonable time | MISSING | UNVERIFIED |

**Only PDP-RIGHT-005 and PDP-RIGHT-007 carry statutory 3×24h operational deadlines. No other right is assigned a 3×24h deadline.**

### 13.2 Procedural & other PDP rules

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| PDP-PROC-001 | Data subject request procedure (permohonan tercatat, elektronik/nonelektronik) — **procedure, not a right** | UU 27/2022 **Ps 14** | REQUIRED | MISSING | UNVERIFIED | |
| PDP-BREACH-001 | Personal data breach notification | UU 27/2022 **Ps 46(1)** | REQUIRED | MISSING | UNVERIFIED | 3×24h |
| PDP-3X24-001 | Consent withdrawal → stop processing within 3×24h | UU 27/2022 **Ps 40(2)** | REQUIRED | MISSING | UNVERIFIED | Operational deadline for PDP-RIGHT-005 |
| PDP-3X24-002 | Restriction/suspension action within 3×24h | UU 27/2022 **Ps 41(1)** | REQUIRED | MISSING | UNVERIFIED | Operational deadline for PDP-RIGHT-007 |
| PDP-3X24-003 | Breach notification within 3×24h | UU 27/2022 **Ps 46(1)** | REQUIRED | MISSING | UNVERIFIED | Same legal basis as PDP-BREACH-001 (obligation vs deadline views) |
| PDP-DPO-001 | DPO / PDP function officer appointment | UU 27/2022 **Ps 53–54** | **CONDITIONALLY REQUIRED** | MISSING | UNVERIFIED | Trigger depends on processing scale/type |
| PDP-DPO-002 | Processor obligations & PDP controller duties | UU 27/2022 Ps 26, 27 | REQUIRED | MISSING | UNVERIFIED | |
| INC-PDP-001 | Personal data breach incident classification | UU 27/2022 **Ps 46** | REQUIRED | MISSING | UNVERIFIED | distinct object from PDP-BREACH-001 (incident vs obligation) |

### 13.3 Deleted/removed non-rules (accepted in review)
- ROPA (record of processing) = **RECOMMENDED**, not mandatory.
- CONSENT_MANAGER / BREACH_RESPONSE_OFFICER removed as statutory roles → capabilities only.
- Strategic-data localization = CONDITIONAL (not blanket Indonesia-only).
- No universal 3×24h for all rights.
- DPO article corrected from Ps 25 (children's data) to **Ps 53–54**.

### 13.4 PDP implementing regulation
As of 13-Aug-2026, the PDP implementing regulation status is **`UNCERTAIN / LEGAL REVIEW`** — transition provisions are in effect; detailed mechanics may change. Preserve as unresolved.

---

## 14. Security Governance (Cluster E)

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| SEC-01 | Data protection by design & default | UU 27/2022 Ps 16 | REQUIRED | PARTIAL | UNVERIFIED | |
| SEC-02 | Security incident response plan | UU 27/2022 Ps 46; PP 71/2019 Ps 24(3) | REQUIRED | MISSING | UNVERIFIED | |
| SEC-03 | Access control & least privilege | PP 71/2019 Ps 11 | REQUIRED | PARTIAL | UNVERIFIED | |
| SEC-04 | Encryption for sensitive data | PP 71/2019 Ps 14; PDP best practice | REQUIRED | PARTIAL | UNVERIFIED | |
| SEC-05 | Availability/continuity | PP 71/2019 Ps 19 | REQUIRED | PARTIAL | UNVERIFIED | |
| SEC-06 | Third-party processor agreements (PDP) | UU 27/2022 Ps 26 | REQUIRED | MISSING | UNVERIFIED | |
| SEC-07 | Vendor/supplier data minimization | UU 27/2022 Ps 19 | REQUIRED | PARTIAL | UNVERIFIED | |

---

## 15. Document & Evidence (Cluster E)

### 15.1 Document inventory & evidence requirements

| Rule ID | Requirement | Regulation | Reg. Applicability | System Readiness | Real-World | Notes |
|---|---|---|---|---|---|---|
| DOC-01 | Legal documents per transaction (RFQ, order, invoice, BAST, payment) | UU ITE Ps 5; PP 71/2019 Ps 22(1) | REQUIRED | PARTIAL (PDF engine exists) | UNVERIFIED | |
| DOC-02 | Electronic record integrity & non-repudiation | UU ITE Ps 5, 11 | REQUIRED | PARTIAL | UNVERIFIED | |
| DOC-03 | Document labelling (mirror vs original; official instrument disclaimer) | Perpres 16/2018; GOV-01 | REQUIRED | PARTIAL | UNVERIFIED | |
| DOC-04 | Evidence retention (see §19.3 matrix) | Tax/PDP/contract rules | REQUIRED | MISSING | UNVERIFIED | |
| DOC-05 | Template versioning & legal review control | General | RECOMMENDED | MISSING | UNVERIFIED | |

### 15.2 Document engine status
Module 7 generates PDFs (RFQ, BAST, invoice). These are internal/support documents. They must be clearly labelled and must not be presented as official procurement instruments (GOV-01/DOC-03).

---

## 16. Roles & Authority (Cluster E)

| Rule ID | Role | Basis | Reg. Applicability | System Readiness | Notes |
|---|---|---|---|---|---|
| ROLE-01 | System Admin (platform) | Internal | REQUIRED | IMPLEMENTED | |
| ROLE-02 | Data Protection Officer (DPO) / PDP function officer | UU 27/2022 Ps 53–54 | CONDITIONALLY REQUIRED | MISSING | Capability + assignment |
| ROLE-03 | Privacy Officer (if DPO not triggered) | Internal best practice | RECOMMENDED | MISSING | Capability not statutory role |
| ROLE-04 | Compliance / Legal reviewer | Internal | RECOMMENDED | MISSING | Approve templates/channels |
| ROLE-05 | Tax officer (faktur/withholding) | DJP obligations | CONDITIONALLY REQUIRED | MISSING | |
| ROLE-06 | Auditor / audit-log viewer | PP 71/2019 Ps 22(1) | REQUIRED | PARTIAL | |
| ROLE-07 | Consent manager (capability, NOT statutory role) | UU 27/2022 | RECOMMENDED | MISSING | Capability only |
| ROLE-08 | Breach response owner (capability, NOT statutory role) | UU 27/2022 Ps 46 | RECOMMENDED | MISSING | Capability only |

---

## 17. Prohibited Behaviors (Cluster E)

| Rule ID | Prohibited behavior | Basis | Reg. Applicability | System Readiness | Notes |
|---|---|---|---|---|---|
| PROH-01 | Presenting internal `ORD-*` docs as official PO | Perpres 16/2018; GOV-01 | REQUIRED (prohibit) | PARTIAL | Labelling control |
| PROH-02 | Presenting non-catalog offers as e-catalog offers | Kepka 122/2022 | REQUIRED (prohibit) | MISSING | |
| PROH-03 | Claiming TKDN/SNI without evidence | Perpres 16/2018 | REQUIRED (prohibit) | MISSING | |
| PROH-04 | Processing data beyond purpose (no purpose limitation) | UU 27/2022 Ps 16, 19 | REQUIRED (prohibit) | PARTIAL | |
| PROH-05 | Processing after consent withdrawal (3×24h) | UU 27/2022 Ps 40(2) | REQUIRED (prohibit) | MISSING | timer |
| PROH-06 | Auto-decisions on data subjects w/o safeguards (no profiling without basis) | UU 27/2022 Ps 10 | CONDITIONALLY REQUIRED | MISSING | |
| PROH-07 | Non-notification of breaches | UU 27/2022 Ps 46 | REQUIRED (prohibit) | MISSING | |
| PROH-08 | Retention beyond lawful basis | Tax/PDP/contract | REQUIRED (prohibit) | MISSING | see §19.3 |

---

## 18. Regulatory Traceability Matrix (Regulation → Article → Rule ID)

### 18.1 UU ITE 11/2008 jo 1/2024
| Article | Rule IDs |
|---|---|
| Ps 5–6 | B2B-01, B2B-02, DOC-01, DOC-02, ETS-02 |
| Ps 11 | B2B-03, B2B-04, DOC-02, PSE-CERT (concept) |
| Ps 12 | (signature legal effect) B2B-03 |
| Ps 18–19 | B2B-05, ETS-01, ETS-03 |

### 18.2 UU 27/2022 (PDP)
| Article | Rule IDs |
|---|---|
| Ps 5–13 | PDP-RIGHT-001..009 |
| Ps 14 | PDP-PROC-001 |
| Ps 16 | PSE-DATA-001, SEC-01, PROH-04 |
| Ps 19 | SEC-07, PROH-04 |
| Ps 26–27 | PDP-DPO-002, SEC-06 |
| Ps 40(2) | PDP-3X24-001, PDP-RIGHT-005 |
| Ps 41(1) | PDP-3X24-002, PDP-RIGHT-007 |
| Ps 46 | PDP-BREACH-001, PDP-3X24-003, INC-PDP-001, SEC-02, PROH-07, ROLE-08 |
| Ps 53–54 | PDP-DPO-001, ROLE-02 |

### 18.3 PP 71/2019 (PSTE)
| Article | Rule IDs |
|---|---|
| Ps 2(2) | PSE classification (AJM = PSE privat) |
| Ps 6(1) | PSE-REG-001 |
| Ps 11, 12, 13, 14 | PSE-GOV-001, PSE-SEC-001, SEC-03, SEC-04 |
| Ps 14(5) | INC-PSE-002 |
| Ps 19 | PSE-GOV-002, SEC-05 (continuity & tata kelola keberlangsungan — NOT Ps 21(1)) |
| Ps 21(1) | PSE-DATA-002 (overseas transfer permitted) |
| Ps 22(1) | PSE-AUDIT-001, B2B-02, DOC-01, ROLE-06 |
| Ps 24(3) | INC-PSE-001, SEC-02 |
| Ps 42–43 | Sertifikat Keandalan / LSK (distinct) |
| Ps 51(1),(3) | PSE-CERT-001 |
| Ps 52–53 | B2B-03, B2B-04 |
| Ps 100 | PSE-SANC-001 |

### 18.4 Permenkominfo 11/2022
| Article | Rule IDs |
|---|---|
| Ps 24(2) | PSE-CERT-001 |
| Ps 25, 26, 29 | PSE-CERT-001 (lifecycle) |
| Ps 3, 33 | PSrE definitions, trust services |

### 18.5 Permenkominfo 5/2020 jo 10/2021
| Article | Rule IDs |
|---|---|
| Ps 4–7 | PSE-REG-002, PSE-REG-003 |

### 18.6 Perpres 16/2018 jo 12/2021 jo 46/2025
| Article | Rule IDs |
|---|---|
| Ps 4(2) | GOV-02 |
| General | GOV-01, GOV-03, GOV-04, LKPP-*, TKDN-*, PROH-01, PROH-03 |
| Perpres 17/2023 | LKPP-03 (INAPROC) |

### 18.7 Kepka LKPP
| Regulation | Rule IDs |
|---|---|
| Kepka 122/2022 | LKPP-01, LKPP-02, LKPP-06, TKDN-01/02 |
| Kepka 177/2024 | LKPP-02 |
| Catalog V6 T&C | LKPP-04, LKPP-05 |
| ~~Kepka 21/2018~~ (jadwal retensi arsip; revoked by Peraturan LKPP 6/2023) | — (LKPP-03 moved to Perpres 17/2023) |

### 18.8 Tax
| Regulation | Rule IDs |
|---|---|
| PMK 11/2025 jo 53/2025 (DPP nilai lain) | TAX-PPN-01 |
| PMK 131/2024 (PPN treatment) | TAX-PPN-01 (rate basis) |
| PER-11/PJ/2025 | TAX-PPN-02, TAX-PPN-03, TAX-PPN-05 |
| PER-1/PJ/2025 | TAX-PPN-03 |
| PMK 81/2024 (SIAP umbrella) | TAX-PPN-03 |
| PMK 59/2022 | TAX-PPN-04 |
| TAX-PPH-03 | UNCERTAIN — professional tax review (not PMK 131/2024) |
| Coretax | TAX-CRT-01 |
| UU PPh & implementing | TAX-PPH-01, TAX-PPH-02 |

### 18.9 Permendag 31/2023 / PP 80/2019
| Regulation | Rule IDs |
|---|---|
| Permendag 31/2023 | PMSE-01, PMSE-02, PMSE-04 |
| PP 80/2019 | PMSE-03 |

---

## 19. Compliance Gap Matrix

Legend — Reg.App: R=REQUIRED, CR=CONDITIONALLY REQUIRED, REC=RECOMMENDED, NA=NOT APPLICABLE, U=UNCERTAIN/LEGAL REVIEW.
System: IMP=IMPLEMENTED, PAR=PARTIAL, MIS=MISSING, NA, UNV=UNVERIFIED.

| Rule ID | Reg.App | System | Gap (priority) |
|---|---|---|---|
| PSE-REG-001 | R | MIS | HIGH — PSE registration |
| PSE-CERT-001 | R | MIS | HIGH — electronic certificate |
| PSE-AUDIT-001 | R | PAR | MEDIUM — map Module 8 → PSE audit trail |
| INC-PSE-001 | R | MIS | HIGH — disruption reporting |
| INC-PSE-002 | R | MIS | HIGH — PDP failure in PSE |
| PDP-RIGHT-001..009 | R | MIS | HIGH — rights fulfillment |
| PDP-3X24-001/002/003 | R | MIS | HIGH — 3×24h timers |
| PDP-PROC-001 | R | MIS | HIGH — request procedure |
| PDP-BREACH-001 | R | MIS | HIGH — breach notification |
| PDP-DPO-001 | CR | MIS | MEDIUM — DPO appointment |
| TAX-PPN-01 | R | PAR | MEDIUM — DPP calc engine |
| TAX-PPN-02 | R | MIS | HIGH — faktur code selection |
| TAX-PPN-03 | R | MIS | HIGH — E-Faktur |
| TAX-PPN-04 | CR | MIS | MEDIUM — B2G collection |
| TAX-PPN-05 | R | MIS | MEDIUM — NPWP/NIK validation |
| TAX-CRT-01 | (opt) | MIS | LOW — optional |
| LKPP-01 | CR | PAR | MEDIUM — e-purchasing default warning |
| LKPP-02 | R | MIS | MEDIUM — category gap matrix |
| TKDN-01/02 | CR | MIS | MEDIUM — evidence attributes |
| GOV-01 | R | PAR | MEDIUM — document labelling |
| GOV-02 | R | MIS | MEDIUM — supplier eligibility |
| B2B-03 | CR | PAR | LOW — signature caution |
| B2B-04 | CR | MIS | MEDIUM — certified TTE |
| SEC-02 | R | MIS | HIGH — incident response |
| PROH-05 | R | MIS | MEDIUM — consent timer |
| PROH-08 | R | MIS | MEDIUM — retention |
| DOC-04 | R | MIS | MEDIUM — retention implementation |

---

## 19a. Rule → System Matrix (Rule ID → Module → Requirement → Current Status)

| Rule ID | Module | Requirement | Current System Status |
|---|---|---|---|
| GOV-01 | 7 (Documents) | Label mirror vs original; official-instrument disclaimer | PARTIAL (PDF engine; no labelling enforcement) |
| GOV-02 | 4 (Companies) | Supplier eligibility (NIB/NPWP/KBLI) | MISSING |
| LKPP-01 | 2 (Orders) | e-purchasing default warning | PARTIAL |
| LKPP-02 | 3 (Products) | Catalog category gap matrix | MISSING |
| TKDN-01/02 | 3 (Products) | TKDN/SNI evidence attributes | MISSING |
| B2B-01/02 | 8 (Audit Logs) | Consent/acceptance capture; record integrity | PARTIAL |
| B2B-04 | 7 (Documents) | Certified TTE support | MISSING |
| PSE-REG-001/002 | 0 (Config/Platform) | PSE registration record | MISSING |
| PSE-CERT-001 | 0 (Config/Platform) | Electronic certificate record + expiry | MISSING |
| PSE-AUDIT-001 | 8 (Audit Logs) | Audit trail mapping to Ps 22(1) | PARTIAL |
| PSE-GOV-001/002 | 1–8 (Platform ops) | Reliability/continuity | PARTIAL |
| INC-PSE-001/002 | 6 (Notifications) | Disruption/breach reporting | MISSING |
| PMSE-01/02 | 4 (Companies) | Supplier license visibility | MISSING |
| TAX-PPN-01 | 5 (Payments) | DPP nilai lain calc | PARTIAL |
| TAX-PPN-02 | 5 (Payments) | Faktur code selection | MISSING |
| TAX-PPN-03 | 5 (Payments) | E-Faktur issuance | MISSING |
| TAX-PPN-04 | 5 (Payments) | B2G VAT collection | MISSING |
| TAX-PPN-05 | 4 (Companies) | NPWP/NIK validation | MISSING |
| PDP-RIGHT-001..009 | 4 (Companies) | Data subject rights fulfillment | MISSING |
| PDP-PROC-001 | 4 (Companies) | Request procedure | MISSING |
| PDP-3X24-001/002/003 | 6 (Notifications) | 3×24h SLA timers | MISSING |
| PDP-BREACH-001 | 6 (Notifications) | Breach notification | MISSING |
| PDP-DPO-001 | 0 (Config) | DPO/PDP function officer | MISSING |
| SEC-01..07 | 1–8 (cross) | Security governance | PARTIAL |
| DOC-04 | 7 (Documents) | Retention policy engine | MISSING |
| ROLE-02 | 0 (Config) | DPO role | MISSING |
| PROH-01..08 | 2/7 (Orders/Docs) | Prohibited-behavior controls | PARTIAL |

---

## 19b. Data Retention Matrix (Document → Tax → PDP → Contract/Evidence → Retention Rule)

| Document / Record | Tax Retention | PDP Retention | Contract/Evidence | Retention Rule (composite) |
|---|---|---|---|---|
| Invoice & faktur pajak (E-Faktur) | 10 years (tax books; UU KUP) | Reasonable per PDP (business justification) | Contract/evidence | 10 years (tax drives); verify PDP lawfulness |
| Payment records / bank statements | 10 years | Reasonable | Evidence | 10 years |
| RFQ / order / PO | Business records (5–10 yr per applicable reg) | Reasonable | 5 years civil limitation (KUHPdt 30-yr caveat for some claims — `REQUIRES PROFESSIONAL LEGAL REVIEW`) | UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE (no blanket default) |
| BAST / delivery evidence | Business records | Reasonable | Evidence | UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE |
| PSE audit logs (Ps 22(1)) | N/A | N/A | PSE operational | Retain per PSE ops continuity; at least statutory/audit period |
| Consent records (PDP) | N/A | Proof of consent — retain while processing + transition | Evidence of legal basis | Retain until processing ends + grace; document in ROPA-style record |
| Data subject request records | N/A | PDP Ps 14 procedures | Evidence | Per request lifecycle + reasonable |
| TKDN/SNI certificates | N/A | N/A | Government procurement evidence | Retain for procurement/audit cycles |
| Financial/tax records (PPN) | 10 years | Reasonable | Evidence | 10 years |
| Employee/PIC personal data | N/A | PDP lawful basis | Contract | End of relationship + statutory grace |

**Rule: retention = longest lawful basis, each basis independently verified; no universal 30-year civil retention. PDP basis must be independently documented (lawful basis, purpose, retention rationale).**

---

## 20. Existing Module 1–8 Impact

### 20.1 Modules and regulatory touchpoints
| Module | Status (MODULES_PROGRESS) | Regulatory touchpoint |
|---|---|---|
| 1 RFQ | IMPLEMENTED | B2B-01/02, ETS-01/02/03, DOC-01 |
| 2 Orders | IMPLEMENTED | GOV-03/04, PROH-01, LKPP-06 |
| 3 Products | IMPLEMENTED | TKDN-01/02, LKPP-05 |
| 4 Companies/Parties | IMPLEMENTED | GOV-02, TAX-PPN-05 |
| 5 Payments/TOP | IMPLEMENTED | TAX-PPN-01, TAX-PPH-01 |
| 6 Notifications | IMPLEMENTED | PDP-3X24-003, INC-PSE-001 (notification channel) |
| 7 Documents (PDF) | IMPLEMENTED | DOC-01/03, GOV-01 |
| 8 Audit Logs | IMPLEMENTED | PSE-AUDIT-001, B2B-02, ETS-02 |

### 20.2 No Module 1–8 code changes in this audit
This audit is read-only for Modules 1–8. All proposals below are **Module 9+ design inputs**.

---

## 21. Proposed PRD Changes (Module 9 — design inputs, NOT applied)

1. Add **Regulatory Applicability vs System Readiness vs Real-World Compliance** status model to every module requirement (§0).
2. Add **PSE compliance feature set**: registration record, electronic certificate flag + expiry, audit-trail mapping to PP 71/2019 Ps 22(1), disruption/breach reporting flows.
3. Add **Data Subject Rights fulfillment module**: request intake (PDP-PROC-001), 3×24h SLA timers (Ps 40(2)/41(1)/46(1)), right-status dashboard.
4. Add **DPO / PDP function officer** role & assignment record (Ps 53–54) with conditional trigger.
5. Add **TaxRule engine** concept — versioned, source-cited, never hardcoded rates (PPN DPP nilai lain, faktur code hierarchy).
6. Add **document labelling standard** — mirror vs original; official-instrument disclaimer; template versioning & legal review sign-off.
7. Add **procurement-channel abstraction** — method, channel (e-catalog vs direct), catalog-status field, warnings.
8. Add **TKDN/SNI evidence attributes** per product (certificate, number, expiry) — no auto-%.

---

## 22. Proposed Architecture Changes (Module 9 — design inputs, NOT applied)

1. **TaxRule service** (deterministic, versioned rule registry; outputs DPP, PPN, faktur code, PPh flags).
2. **PDP service** (rights requests, consent records, withdrawal, restriction, portability export, erasure; SLA timers).
3. **Breach/incident service** (INC-PDP-001, INC-PSE-001/002; notification templates; 3×24h timers).
4. **Evidence/document registry** (integrity hashes, retention policy engine per §19b).
5. **Audit-trail adapter** — formal mapping of Module 8 audit log to PP 71/2019 Ps 22(1) obligations.
6. **Supplier-eligibility service** (NIB/NPWP/NIK, KBLI, catalog status).
7. **Certificate registry** (PSE certificate, Sertifikat Elektronik, PSrE info; distinct from user TTE).
8. **Labeling/interceptor** — enforce GOV-01/DOC-03/PROH-01 (no ORD as official PO; no non-catalog as e-catalog).

---

## 23. Proposed Database Changes (Module 9 — design inputs, NOT applied)

Candidate entities (design inputs only; **no migration created**):
- `pse_registration`, `pse_certificates` (PSE-CERT-001)
- `data_subject_requests`, `consent_records`, `consent_withdrawals`, `restriction_records`, `breach_notifications`
- `retention_policies`, `document_evidence`
- `tax_rules` (versioned), `faktur_codes`
- `supplier_eligibility`, `product_certifications` (TKDN/SNI)
- `dp_roles` (DPO/privacy/tax/compliance officers)
- `audit_trail_mapping`
- `incident_register`

All such changes remain **proposals** until Module 9 design is approved; none are implemented by this audit.

---

## 24. Module 9+ Candidate Requirements

### 24.1 Candidate modules
1. **Regulatory/PSE Compliance** (registration, certificate, audit-trail mapping, incident reporting)
2. **PDP/Privacy Fulfillment** (rights, consent, 3×24h timers, DPO)
3. **Tax Engine** (PPN DPP, faktur code, PPh flags, B2G collection)
4. **Supplier & Product Certification** (TKDN/SNI, catalog status, eligibility)
5. **Document & Evidence Governance** (labelling, retention, template control)
6. **Procurement-Channel Intelligence** (method abstraction, e-purchasing defaults + warnings, category gap matrix)
7. **Security Governance** (access, encryption, continuity, incident response)
8. **Regulatory Change Management** (§25)

### 24.2 Automation Candidate Matrix

| # | Rule | Automation class | Deterministic? | Human review? | Legal review? |
|---|---|---|---|---|---|
| 1 | Tax calculation (PPN DPP nilai lain) | TaxRule engine | YES (deterministic with versioned rules) | Exception review | Before rate change |
| 2 | Faktur code selection | Rule engine (hierarchy) | YES | Exception review | On rule change |
| 3 | Procurement-channel warnings | Rule engine | YES (catalog-status dependent) | YES (PPK assessment nuance) | Yes |
| 4 | Document checklist | Rule engine | YES | No | On template change |
| 5 | TKDN/SNI validation (expiry) | Rule engine | YES (expiry) / NO (authenticity) | YES (authenticity) | Yes |
| 6 | PSE/PDP controls | PDP service | PARTIAL | YES | Yes |
| 7 | Breach timers (3×24h) | Deterministic timers | YES | No (execution) | On legal change |
| 8 | Consent withdrawal timers | Deterministic timers | YES | No | On legal change |
| 9 | Transaction evidence validation | Rule engine (integrity) | YES | No | On standards change |
| 10 | Audit-trail controls | Deterministic (logging) | YES | No | No |

---

## 25. Regulatory Change Management

### 25.1 Tracked watch items (13-Aug-2026)
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

### 25.2 Mechanism (proposed, Module 9)
- Versioned `tax_rules` and `regulatory_reference` tables; effective-dated; each change signed off by professional reviewer; changelog entries referencing source.
- Annual (or trigger-based) re-verification of all `CONFIRMED` citations.

---

## 26. Professional Legal/Tax Review Items (preserved, unresolved)

| # | Item | Type | Reason |
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

These items are **not resolved by guesswork**; they remain `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW`.

---

## 27. Final Compliance Readiness Assessment

### 27.1 Verdict

> Is the current AJM platform sufficiently specified from a regulatory and procurement perspective to begin designing Module 9?

**Verdict: YES WITH CONDITIONS**

**Rationale:** Modules 1–8 provide a solid, well-tested foundation (audit logs, document engine, payments, notifications). The audit identified no architectural blocker to Module 9. The **conditions** are the top-10 gaps below, which must be treated as Module 9 **design inputs** (not blockers on analysis). This is a **specification-readiness verdict, NOT a legal-compliance certification.**

### 27.2 Top 10 regulatory/business-rule gaps that must be resolved before Module 9

| # | Gap | Rule IDs | Class |
|---|---|---|---|
| 1 | PSE registration record & PSE electronic certificate (Sertifikat Elektronik via PSrE Indonesia) | PSE-REG-001, PSE-CERT-001 | Regulatory |
| 2 | Data Subject Rights fulfillment incl. 3×24h deadlines (withdrawal Ps 40(2), restriction Ps 41(1), breach Ps 46(1)) | PDP-RIGHT-001..009, PDP-3X24-001/002/003 | Regulatory |
| 3 | PDP breach notification & incident flows (PDP + PSE serious disruption) | PDP-BREACH-001, INC-PDP-001, INC-PSE-001/002 | Regulatory |
| 4 | TaxRule engine: PPN DPP nilai lain (not 12% blanket), faktur code hierarchy, B2G collection | TAX-PPN-01/02/03/04 | Tax |
| 5 | Faktur code selection (hierarchy; code 01 default; 02 only govt collectors; 03 only designated) | TAX-PPN-02 | Tax |
| 6 | DPO / PDP function officer appointment (conditional) | PDP-DPO-001, ROLE-02 | Regulatory |
| 7 | Document labelling (mirror vs original; no `ORD-*` as official PO; no non-catalog as e-catalog) | GOV-01, PROH-01, PROH-02, DOC-03 | Procurement/Integrity |
| 8 | Supplier eligibility & TKDN/SNI evidence attributes (no auto-%) | GOV-02, TKDN-01/02, PROH-03 | Procurement |
| 9 | Retention policy engine (tax/PDP/contract/evidence matrix) | DOC-04, PROH-08, §19b | Cross-cutting |
| 10 | Audit-trail mapping of Module 8 to PP 71/2019 Ps 22(1) | PSE-AUDIT-001, ROLE-06 | Regulatory |

### 27.3 Confidence summary
- `CONFIRMED` legal obligations with primary sources: PSE registration & certificate, PDP rights & 3×24h, PPN/faktur rules, audit trail, incident reporting.
- `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW`: 12 items (§26) — deliberately unresolved.
- Real-world AJM status for all obligations: **UNVERIFIED** (no assertions of non-compliance without evidence).

---

## 28. References (Official Primary Sources)

| Source | Domain / Ref |
|---|---|
| UU 11/2008 jo UU 1/2024 (ITE) | jdih.komdigi.go.id (`view/id/167`) |
| UU 27/2022 (PDP) | jdih.komdigi.go.id; pasal.id; appdi.or.id (salinan) |
| PP 71/2019 (PSTE) | jdih.komdigi.go.id (`view/id/695`) |
| PP 80/2019 (PMSE) | jdih.setkab.go.id |
| Perpres 16/2018 jo 12/2021 jo 46/2025; Perpres 17/2023 (INAPROC) | jdih.lkpp.go.id |
| Peraturan LKPP 6/2023 (pencabutan peraturan kearsipan); Kepka LKPP 122/2022, 177/2024 | jdih.lkpp.go.id |
| Catalog V6 T&C | catalog.lkpp.go.id |
| Permenkominfo 5/2020 jo 10/2021 (PSE) | jdih.komdigi.go.id |
| Permenkominfo 11/2022 (Sertifikasi Elektronik) | jdih.komdigi.go.id (`view/id/833`) |
| PMK 11/2025 jo 53/2025 (DPP nilai lain) | jdih.kemenkeu.go.id |
| PMK 131/2024 (PPN treatment); PMK 81/2024 jo 54/2025 jo 1/2026 (SIAP/Coretax) | jdih.kemenkeu.go.id |
| PER-1/PJ/2025 (Petunjuk Teknis Faktur Pajak); PER-11/PJ/2025 (Pelaporan SIAP) | jdih.kemenkeu.go.id |
| PMK 59/2022 (B2G PPN) | jdih.kemenkeu.go.id |
| Permendag 31/2023 (PMSE) | jdih.kemendag.go.id |
| BSSN (BSrE) | jdih.bssn.go.id; rootca.id (CPS PSrE Induk) |
| PSrE / Sertifikat Elektronik operational | tte.komdigi.go.id; pse.kominfo.go.id |

---

### Appendix A — Cluster Completion Record

| Cluster | Scope | Status | Verification |
|---|---|---|---|
| A | Legal entity / Government procurement | COMPLETE | Approved w/ corrections |
| B | LKPP / INAPROC / E-Catalog / TKDN / SNI | COMPLETE | Approved w/ refinements; QC 14-Aug-2026: INAPROC basis = Perpres 17/2023 (not Kepka LKPP 21/2018, a revoked archival-retention rule) |
| C | Electronic transactions / PSE / PMSE / TTE | COMPLETE | Approved w/ refinements; QC 14-Aug-2026: PSE-GOV-002 continuity basis = PP 71/2019 Ps 19 (Ps 21(1) is overseas-processing, NOT DR) |
| D | Tax / DJP / PPN / PPh / Coretax | COMPLETE | Approved w/ corrections; QC 14-Aug-2026: corrected PMK 131/2024 / PMK 81/2024 / PER-1/PJ/2025 / PER-11/PJ/2025 titles & mappings; TAX-PPH-03 basis UNCERTAIN |
| E | PDP / Security / Documents / Roles / Prohibitions / Change mgmt | COMPLETE | Approved w/ final micro-corrections (3×24h, PSE-CERT-001, Rule-ID uniqueness) |

### Appendix B — Rule ID Uniqueness (final check, 13-Aug-2026)

**Cluster E core set (PDP / PSE / INC prefixes): 32 unique Rule IDs, zero duplicates** (approved in final review; enumerated below).

Full cross-cluster set — **98 discrete Rule IDs referenced across all clusters, zero duplicates**:

```
GOV-01..05            LKPP-01..06          TKDN-01..05          B2B-01..05
ETS-01..03            PSE-REG-001..003     PSE-GOV-001..003     PSE-DATA-001..003
PSE-AUDIT-001         PSE-SEC-001          PSE-CERT-001         PSE-SANC-001
INC-PSE-001/002       INC-PDP-001          PMSE-01..04
TAX-PPN-01..06        TAX-PPH-01..03       TAX-CRT-01
PDP-RIGHT-001..009    PDP-PROC-001         PDP-BREACH-001
PDP-3X24-001..003     PDP-DPO-001/002      SEC-01..07
DOC-01..05            ROLE-01..08          PROH-01..08
```

**Note 1:** `PDP-BREACH-001` (obligation) vs `INC-PDP-001` (incident classification) share Ps 46 as basis but are distinct objects with distinct IDs — intentional.
**Note 2:** The 32-ID Cluster E core set is a subcount of the 98 cross-cluster total; the higher figure includes Cluster A–D rule prefixes (GOV/LKPP/TKDN/B2B/ETS/PMSE/TAX/SEC/DOC/ROLE/PROH).
**Note 3:** The earlier review recorded "33" for the Cluster E core set; a recount of the enumerated IDs gives 32. The zero-duplicates guarantee is unaffected by this count correction.

---

## 29. Consolidated QC Findings (14-Aug-2026)

Scope: citation/consistency QC only, on top of prior cluster approvals. All corrections below verified against official sources (JDIH LKPP, JDIH Kemenkeu, JDIH Komdigi) as of 14-Aug-2026.

### 29.1 Corrected rules & citations (applied inline)

| Location | Was | Now (verified) |
|---|---|---|
| §4 row 6; §6.1; LKPP-03; §18.6/18.7; §28 | INAPROC = "Kepka LKPP 21/2018" | **INAPROC legal basis = Perpres 17/2023** (Percepatan Transformasi Digital Pengadaan; INAPROC developed by LKPP + PT Telkom). Peraturan LKPP 21/2018 is the **Jadwal Retensi Arsip LKPP** (archival-retention), **revoked by Peraturan LKPP 6/2023** — historical only. |
| §4 row 13; TAX-PPN-03/PH-03 mapping | PMK 131/2024 = "E-Faktur / sertifikat elektronik perpajakan" | PMK 131/2024 = **PPN treatment** (Impor/Penyerahan BKP & JKP, Pemanfaatan BKP Tidak Berwujud & JKP dari Luar Daerah Pabean). Basis for TAX-PPN-01 rate treatment; NOT an E-Faktur procedure. |
| §4 row 14 | PMK 81/2024 = "tata cara E-Faktur" | PMK 81/2024 = **Ketentuan Perpajakan dalam Rangka Pelaksanaan SIAP/Coretax**, jo PMK 54/2025 (Perubahan Ketiga), jo PMK 1/2026 (Perubahan Keempat). |
| §4 row 15; TAX-PPN-05 | PER-1/PJ/2025 = "NPWP/NIK & identitas" | PER-1/PJ/2025 = **Petunjuk Teknis Pembuatan Faktur Pajak** dalam pelaksanaan PMK 131/2024. Counterparty NPWP/NIK identity (TAX-PPN-05) lives in **PER-11/PJ/2025**. |
| §4 row 16 | PER-11/PJ/2025 = "faktur pajak, Lampiran D code hierarchy" | PER-11/PJ/2025 = **Pelaporan PPh, PPN, PPnBM & Bea Meterai dalam SIAP**; Lampiran D = kode & nomor seri faktur pajak (confirms TAX-PPN-02 citation). |
| TAX-PPN-03 | E-Faktur issuance = "PMK 131/2024, PMK 81/2024" | E-Faktur issuance & reporting = **PER-1/PJ/2025 + PER-11/PJ/2025 + PMK 81/2024 (SIAP umbrella)**. |
| TAX-PPH-03 | Tax certificate/NPWP evidence = "PMK 131/2024" | **UNCERTAIN — `REQUIRES PROFESSIONAL TAX REVIEW`** (PMK 131/2024 removed; it is PPN treatment, not certificate/NPWP evidence). |
| PSE-GOV-002; §18.3 | Continuity & DR = "Ps 19, 21(1)" | **PP 71/2019 Ps 21(1) = overseas processing/storage permitted — NOT a disaster-recovery mandate.** Continuity basis = **Ps 19** (tata kelola & keberlangsungan). DR is engineering best practice layered on Ps 19 obligations; PSE-DATA-002 retains Ps 21(1) (overseas transfer permitted). |
| §3 row | Tax status "PKP on sell-side" | **UNVERIFIED/assumed** — PKP is a condition for e-Faktur issuance, subject to AJM DJP registration evidence (System Readiness vs Real-World split preserved). |
| §19b rows | "10 years default (safe)" for RFQ/PO/BAST | **UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE.** Invoice/faktur pajak 10-yr tax basis (UU KUP) retained; blanket default removed; DOC-04 retention engine = future capability only. |
| §4 | "33 unique Rule IDs" | **98 discrete Rule IDs** (see Appendix B); Cluster E core = 32. Count corrected; zero duplicates. |

### 29.2 Remaining UNCERTAIN / open items (preserved, not resolved)

- **TAX-PPH-03** — exact legal basis for counterparty tax-certificate/NPWP evidence: `REQUIRES PROFESSIONAL TAX REVIEW`.
- **PMK 81/2024 amendment numbers** — Perubahan Pertama/Kedua not individually verified (PMK 11/2025 & PMK 53/2025 referenced in PMK 1/2026 preamble as prior amendments); document cites the verified chain (PMK 54/2025 = Perubahan Ketiga; PMK 1/2026 = Perubahan Keempat).
- All §26 professional-review items remain open and are re-confirmed unchanged.
- PDP implementing regulation (UU 27/2022 transition), Coretax integration approach, PMSE classification, TKDN/SNI supplier-evidence scope, PPh 23 exact model — all `REQUIRES PROFESSIONAL LEGAL/TAX REVIEW` as documented.

### 29.3 Final QC recommendation

**APPROVED FOR RULEBOOK — SUBJECT TO CONDITIONS.**

The audit is citation-consistent and internally consistent after the QC pass: every contested citation was traced to an official source and corrected inline; no rule was invented, no cluster restarted, and the three-status model / `YES WITH CONDITIONS` verdict / 98-rule (32 core) uniqueness are intact. Conditions (unchanged from §26):
1. Resolve TAX-PPH-03 legal basis via professional tax review before the Rulebook cites it.
2. Verify AJM's PKP/DJP registration status for sell-side e-Faktur before claiming real-world compliance.
3. Keep all UNCERTAIN items labelled; do not upgrade to REQUIRED without a documented legal basis.
4. Do not hardcode tax rates or retention defaults in Module 9 — use the TaxRule engine and document-specific retention policies.
