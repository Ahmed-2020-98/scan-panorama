# Scan4Dent MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the user's twenty sections as an extensible, branch-aware radiology MVP with fast case registration, four roles, accounting, Drive files/video, visits, audit history, and recoverable deletion.

**Architecture:** Extend the existing Laravel application with small domain services for visibility, registration, pricing, sharing, accounting, and file storage. Keep Livewire pages and existing policies; policies plus explicit actor-aware query scopes enforce record visibility, including Livewire requests and downloads. New storage metadata identifies each file's backend so existing local files continue to resolve independently of the new-upload provider.

**Tech Stack:** PHP ^8.3, Laravel ^13.17, Livewire ^4.1, Flux ^2.13.1, Tailwind 4, SQLite locally and MySQL/MariaDB in production; Google Drive API v3.

**Spec:** `docs/superpowers/specs/2026-10-01-radiology-mvp.md`

**Design:** `docs/superpowers/specs/2026-10-01-radiology-design.md`

## Global Constraints

- Main registration action: «حالة جديدة»; phone and optional clinical/demographic fields must not block draft creation.
- Four roles only: Manager, Reception, Doctor, Technician; multiple Managers supported.
- Full financial access only for Manager. Reception sees its permitted current business day; Doctor and Technician never receive financial data, even with permission overrides.
- All non-Manager work is constrained by assigned branches and, for Doctor/Technician, case assignment unless the user explicitly chooses broader Doctor access.
- New files are uploaded from the application to Drive, organized under Center / Branch / Year / Patient / Case and Images / Reports / DICOM / Videos / Requests.
- One-way Drive upload in MVP; no manual Drive synchronization, WhatsApp API, advanced finance, or mobile app.
- Soft deletion must be recoverable and audited; never destroy original files on a normal deletion.
- Preserve existing data with additive migrations and explicit data backfills; never run migrate:fresh on the project database.
- العربية وRTL، واجهات مناسبة للهاتف، وعرض أخطاء الحقول مع الاحتفاظ بالمدخلات.
- Prices use integer minor units, configured currency, immutable historical snapshots, and transactional audit entries.
- This repository currently has no `.git`; commits/worktrees are unavailable until a repository is initialized by an authorized action.

## Review Focus

1. Draft cases missing doctor/exam/name must open in all tables, patient pages, portal, and shares without null dereferences or misidentifying anonymous patients.
2. A user changing a URL, branch ID, Livewire public property, date range, or download ID must not escape record scope or financial restrictions.
3. Price changes, discount calculations, and simultaneous payments must preserve arithmetic and actor/time history; unpriced legacy cases are not silently valued as zero.
4. Interrupted/retried Drive uploads and identical filenames must not cause lost bytes, duplicate attachments, orphaned ready records, or indefinite temporary-file retention.
5. Patient/case/file deletion and restore must preserve existing independent deletions, revoke normal/share access while deleted, and retain financial/audit records.

## Existing implementation findings

- `cases/⚡form.blade.php` already creates a patient inside a case transaction, but doctor/branch/exam/date and patient name are required.
- `MedicalCasePolicy` currently grants all staff every case; branch isolation needs both query and object-level checks.
- `CaseStatus` is derived from files and sharing; it cannot represent technician workflow without a separate stored field.
- Patient and case use SoftDeletes; `CaseFile` does not, and its deleted event destroys stored bytes.
- `patients/⚡show.blade.php` blocks deletion when any case exists; it has no per-case sharing controls or file/video expansion.
- Share tokens are already bound to cases. Extend that implementation rather than create unrelated WhatsApp links.
- Audit actions already exist but are invoked inconsistently across patient/user/reference mutations; the log must retain before/after changes.
- Exam types have category/order/active, but no description or pricing. There is no financial or visits module.

## Delivery groups and dependency order

The independent subsystems are grouped into identity/access (tasks 1–2), case/patient workflow (3–4), finance (5–6), files/sharing/recovery (7–9), and visits/dashboard (10–11). Each task produces a working bounded deliverable. Task 12 integrates and validates the complete MVP. Unanswered business choices in the spec are resolved before the task that depends on them.

### Task 1: Add schema and preserve existing data

**Files:** Create `database/migrations/2026_10_01_000001_add_branch_access.php`, `...000002_add_case_workflow_and_pricing.php`, `...000003_add_drive_storage_and_recovery.php`, `...000004_create_payments_and_doctor_visits.php`; modify `app/Enums/Role.php`, model Fillable/casts, factories and seeders. Test: `tests/Feature/MvpMigrationTest.php`.

**Interfaces:** Produces users.branch_id and permission_overrides JSON; patient_branch unique pivot; branch_exam_type unique pivot with is_active and base_price_minor; exam_types.base_price_minor/description; medical_cases nullable doctor_id/exam_type_id, technician_id, workflow_status, medical_notes/technical_notes, base_price_minor/discount_minor/final_price_minor, discount_reason/by/at; case_files.disk/provider_id/provider_folder_id/storage_status/is_shared/deleted_at; upload_sessions durable identity/status metadata; payments (case_id, branch_id, amount_minor, currency, method, collected_by, received_at, unique request_id, adjustment_of_id); doctor_visits; drive_folders with unique logical key and provider_id. Soft-delete recovery adds deletion_batch_id to patient/case/file records. Branch and patient IDs remain real references.

- [ ] Step 1: Add `test_existing_rows_survive_role_and_storage_backfill`: admin becomes manager, legacy file disk remains local, patient_branch derives from case branches, legacy workflow becomes new, and legacy prices remain null.
- [ ] Step 2: Run `php artisan test --filter=MvpMigrationTest`; expect missing-column/enum assertions to fail on a temporary database.
- [ ] Step 3: Implement additive migrations with indexed foreign keys, role backfill, existing configured file-disk backfill, patient pivot backfill, and transaction-safe migration logic for SQLite/MySQL. Back up the local SQLite file before applying migrations to the workspace database.
- [ ] Step 4: Run the migration test; expect PASS and row counts/data identities unchanged. Populate no invented prices or revenue.

### Task 2: Implement roles, flexible permissions, and branch access

**Files:** Create `app/Enums/Permission.php`, `app/Support/AccessScope.php`, `app/Http/Middleware/EnsurePermission.php`, `app/Policies/PatientPolicy.php`; modify `User.php`, existing case/file policies, `routes/web.php`, `bootstrap/app.php`, users page, sidebar, doctor form. Tests: `tests/Feature/BranchAccessTest.php`, `tests/Feature/PermissionOverrideTest.php`.

**Interfaces:** `User::hasPermission(Permission $permission): bool`; `AccessScope::cases(User $actor): Builder`; `AccessScope::patients(User $actor): Builder`; `AccessScope::branches(User $actor): Builder`; `AccessScope::allowsCase(User $actor, MedicalCase $case): bool`. Manager sees all; Reception sees its assigned branch; Technician sees branch plus assigned technician_id; Doctor sees branch plus doctor_id by proposed default. No assigned branch means denied operational access, never all branches. Patient queries and their case/file counts use visible cases, including shared patients across branches.

- [ ] Step 1: Add `test_branch_id_tampering_is_rejected_in_routes_and_livewire`, `test_unassigned_staff_has_no_branch_access`, `test_doctor_and_technician_cannot_gain_financial_access`, `test_last_active_manager_cannot_be_removed`; assert 403 and zero leaked IDs/amounts.
- [ ] Step 2: Run `php artisan test --filter='BranchAccessTest|PermissionOverrideTest'`; expect failure before implementation.
- [ ] Step 3: Implement role defaults, typed permission allow/deny overrides, record-scope rules, and per-action authorization. Route permissions and policies must run again for Livewire mutations; locked IDs alone are insufficient. Add user creation for all four roles, optional Doctor profile creation, branch assignment, activation and permissions editing. Deny financial role overrides for Doctor/Technician.
- [ ] Step 4: Run the task tests plus `AccessControlTest`/`CaseFileAccessTest`; expect allowed-role actions and denied cross-branch accesses.

### Task 3: Unified fast patient/case registration

**Files:** Create `app/Support/PatientAge.php`, `app/Services/CaseRegistration.php`; modify patient/case models, case form, patient form/index, Dashboard registration actions, case/patient/portal/shared renderers. Tests: `tests/Feature/QuickRegistrationTest.php`, `tests/Unit/PatientAgeTest.php`.

**Interfaces:** `PatientAge::yearFromAge(int $age, int $currentYear): int`; `PatientAge::ageFromYear(int $year, int $currentYear): int`; `CaseRegistration::save(User $actor, array $data, ?MedicalCase $case = null): MedicalCase`. Data includes existing patient_id OR optional inline demographics, automatically scoped branch, optional doctor/exam/technician, exam date default today, notes. Transaction creates/link patient, case, pivot, and audit atomically.

- [ ] Step 1: Add `test_draft_registration_without_phone_doctor_or_exam_opens_everywhere`, `test_separate_anonymous_patients_get_distinct_file_numbers`, and `test_year_and_age_use_same_reference_year`. For currentYear=2026, age=70 gives year=1956, year=1950 gives age=76; reject future years/negative ages and preserve an existing full birth date if untouched.
- [ ] Step 2: Run `php artisan test --filter='QuickRegistrationTest|PatientAgeTest'`; expect failures on required fields/date behavior.
- [ ] Step 3: Implement one «حالة جديدة» entry, inline search and new patient, age/year synchronization with last edited field as source, automatic date/branch, optional fields, incomplete-data indicator and null-safe case rendering. Patient section remains for history/editing; redirect legacy patient-create entry to unified case flow. Never allow an existing patient selection from an unauthorized branch.
- [ ] Step 4: Run task tests plus `CaseManagementTest`; expect no null crashes and no partial patient row after failed case save.

### Task 4: Technician work and Doctor medical updates

**Files:** Create `app/Enums/WorkflowStatus.php`, `app/Services/CaseClinicalUpdates.php`, `resources/views/pages/technician/⚡index.blade.php`; modify portal case and staff case pages, User homeUrl, routes, existing policies. Test: `tests/Feature/ClinicalWorkflowTest.php`.

**Interfaces:** `CaseClinicalUpdates::update(User $actor, MedicalCase $case, array $data): void`; fields are explicitly allowlisted by role. Technician can change workflow_status/technical_notes; Doctor can change medical_notes/report text and upload report assets; Reception/Manager can assign doctor/technician and edit operational fields. New/active/completed states are independent of sharing.

- [ ] Step 1: Add `test_technician_completes_assigned_case_only`, `test_doctor_changes_medical_fields_but_not_pricing_or_internal_notes`, and `test_upload_does_not_implicitly_complete_case`.
- [ ] Step 2: Run `php artisan test --filter=ClinicalWorkflowTest`; expect failures before workflow support.
- [ ] Step 3: Add assignment pickers scoped by branch, explicit medical/technical note persistence and audit, Technician work queue, Doctor report/note controls and authorized upload. Filter response/Livewire state so restricted fields are never serialized to Doctor/Technician.
- [ ] Step 4: Run task tests; expect audited transitions and rejected mass-assigned fields.

### Task 5: Exam catalog, branch prices, and auditable case discounts

**Files:** Create `app/Services/CasePricing.php`; modify ExamType, Branch relationships, exam-types/branches/case forms and ActivityLog labels. Test: `tests/Feature/CasePricingTest.php`.

**Interfaces:** `CasePricing::quote(ExamType $exam, Branch $branch): ?int`; `CasePricing::apply(User $actor, MedicalCase $case, int $baseMinor, int $finalMinor, ?string $reason): void`; all money in integer minor units, discount=base-final. Actor/time/before/after price snapshots saved atomically. Existing exam category/order retained.

- [ ] Step 1: Add `test_branch_override_beats_catalog_price`, `test_1000_to_500_discount_records_reason_actor_and_time`, `test_catalog_change_keeps_existing_case_price`, and `test_discount_requires_permission_and_reason`; reject negative/final>base discounts and malformed money.
- [ ] Step 2: Run `php artisan test --filter=CasePricingTest`; expect missing-price/service failures.
- [ ] Step 3: Implement catalog base price/description, per-branch availability/price overrides, auto quotation on exam choice, case snapshot, edit/discount permissions, and immutable audit values. Price recording does not grant Reception unrestricted discount permission. Block final-price changes below collected payments unless a separate Manager refund adjustment exists.
- [ ] Step 4: Run task tests; expect exact minor-unit arithmetic and actor history. Validate chosen currency in center settings before enabling real money entry.

### Task 6: Accounting and daily/monthly reports

**Files:** Create `app/Models/Payment.php`, `app/Services/CasePayments.php`, `app/Services/FinancialReport.php`, `app/Policies/PaymentPolicy.php`, `resources/views/pages/admin/⚡accounts.blade.php`; modify routes/sidebar/case financial panel. Test: `tests/Feature/AccountingAccessTest.php`, `tests/Feature/PaymentLedgerTest.php`.

**Interfaces:** `CasePayments::receive(User $actor, MedicalCase $case, int $amountMinor, string $method, string $requestId): Payment`; `FinancialReport::summarize(User $actor, array $filters): array`. Summary outputs separate billed, discounted, collected, outstanding, unpriced counts, and exam counts. Reception effective period forced to current day and allowed branch; Manager may choose historical dates/all branches. Never trust posted filters.

- [ ] Step 1: Add `test_reception_cannot_request_yesterday_or_another_branch`, `test_partial_payment_and_duplicate_request_are_safe`, `test_deleted_case_keeps_financial_history`, and `test_unpriced_case_is_counted_separately`; after final=500 and payment=200, outstanding=300.
- [ ] Step 2: Run `php artisan test --filter='AccountingAccessTest|PaymentLedgerTest'`; expect failures without ledger/reports.
- [ ] Step 3: Implement transactional, idempotent payments with row locking and overpayment rejection; signed Manager adjustments preserve original entries. Daily/monthly reports use payment timestamps for collections and exam dates for activity. Scope report exports exactly as the view and neutralize spreadsheet formula injection in CSV values. Finalize revenue interpretation after the user's answer.
- [ ] Step 4: Run task tests; expect Doctor/Technician 403 and no financial amounts in their rendered or Livewire data.

### Task 7: Drive storage, resumable transfer, and unified video uploads

**Files:** Create `app/Contracts/CaseStorage.php`, `app/Services/Drive/DriveClient.php`, `app/Services/Drive/DriveCaseStorage.php`, `app/Services/Drive/DriveFolderResolver.php`, `app/Models/UploadSession.php`, `app/Jobs/UploadCaseFileToDrive.php`, `app/Http/Controllers/DriveConnectionController.php`, `config/drive.php`; modify upload controller, FileResponder, CaseFileType/CaseFile, radiology config, uploader JS/component, scheduler and `.env.example`. Tests: `tests/Feature/DriveUploadTest.php`, `tests/Feature/VideoUploadTest.php`.

**Interfaces:** `CaseStorage::store(CaseFile $file, string $stagingPath): void`; `CaseStorage::stream(CaseFile $file, string $mode): StreamedResponse`; `DriveFolderResolver::caseFolder(MedicalCase $case, CaseFileType $type): string`. Job takes CaseFile ID and UploadSession ID, resolves current authorization/deletion state, uploads using Drive resumable API and atomically marks ready with provider IDs. Durable transfer state owns the staging path through bounded retries.

- [ ] Step 1: Add `test_drive_retry_does_not_duplicate_attachment`, `test_same_filename_uses_distinct_provider_ids`, `test_cross_case_chunk_session_is_rejected`, `test_pending_files_are_not_shared`, `test_legacy_local_files_still_open`, `test_video_range_request_and_mime_validation` with fake HTTP responses and synthetic file bytes.
- [ ] Step 2: Run `php artisan test --filter='DriveUploadTest|VideoUploadTest'`; expect unsupported-provider/video failures.
- [ ] Step 3: Implement OAuth connection for Gmail/My Drive or configured Shared Drive identity after account choice, encrypted refresh tokens, exact redirect/state validation and Manager-only settings. Resolve folders by stable IDs/logical unique keys, not names alone. Introduce images/report/referral/DICOM/video classifications while preserving old report-with-images records. Add MP4/WebM/MOV with MIME checks and configured size limits. Bind chunk session to actor/case/type/name/size and lock assembly/finalization. Transfer by resumable streaming with retry/backoff; clean staging after verified transfer or bounded abandoned-session retention. Keep upload failure visible and retryable. Persist each file's storage backend.
- [ ] Step 4: Run task tests plus ChunkedUploadTest and CaseFileAccessTest; expect verified private streaming and bounded memory use. Document queue worker/scheduler and external connection setup. Real Google acceptance is deferred until connected; simulated tests cannot claim live Drive success.

### Task 8: Patient history and case-bound sharing

**Files:** Create `app/Services/CaseSharing.php`, `resources/views/components/case-share-panel.blade.php`; modify patient show, case show, SharedCaseController, shared views and FileResponder. Test: `tests/Feature/PatientSharingTest.php`.

**Interfaces:** `CaseSharing::updateSelection(User $actor, MedicalCase $case, array $fileIds): void`; `CaseSharing::regenerate(User $actor, MedicalCase $case): string`; selection only accepts this case's ready/live files. Existing routes `shared.show`/`shared.file` and expiry/revocation remain.

- [ ] Step 1: Add `test_patient_history_only_shows_visible_cases`, `test_share_contains_only_selected_ready_files`, `test_added_or_removed_selected_files_update_existing_link`, `test_foreign_file_id_is_rejected`, and `test_deleted_case_or_patient_invalidates_access`.
- [ ] Step 2: Run `php artisan test --filter=PatientSharingTest`; expect missing controls/selection failures.
- [ ] Step 3: Build patient demographic/year summary, previous exams, expandable files/videos and per-case sharing panel (generate/copy/WhatsApp/open/revoke/selected files). Show pending upload status and expiry. Newly ready files are included by default unless explicitly excluded. Send buttons only open a WhatsApp draft; do not claim delivery from merely clicking. Keep Drive files private and serve through scoped application routes, including range support for video where supported.
- [ ] Step 4: Run task tests plus ShareLinkTest; expect old token rejection after regeneration and no access to another case's files.

### Task 9: Recoverable deletion and complete audit history

**Files:** Create `app/Services/RecordRecovery.php`, `resources/views/pages/admin/⚡recycle-bin.blade.php`; modify CaseFile deleted event, patient/case actions, ActivityLogger/ActivityLog, activity page and routes/sidebar. Tests: `tests/Feature/RecordRecoveryTest.php`, `tests/Feature/MvpAuditTest.php`.

**Interfaces:** `RecordRecovery::deletePatient(User $actor, Patient $patient): void`; `deleteCase(User $actor, MedicalCase $case): void`; `deleteFile(User $actor, CaseFile $file): void`; `restore(User $actor, Model $record): void`. Record deletion origin/batch so parent restoration restores only children deleted by that operation. Retain payment/audit history and file bytes; Manager-only trash visibility.

- [ ] Step 1: Add `test_restore_parent_does_not_restore_independently_deleted_file`, `test_soft_deleted_file_bytes_remain`, `test_patient_delete_hides_all_related_cases_and_links`, and `test_all_mutations_capture_actor_before_after_and_timestamp`.
- [ ] Step 2: Run `php artisan test --filter='RecordRecoveryTest|MvpAuditTest'`; expect permanent-file-deletion/current-patient-block failures.
- [ ] Step 3: Implement transaction-bound soft deletion/recovery and audit for patient/case/file/user/price/discount/visit/reference mutations; remove normal-delete physical file destruction. Audit dates never depend on updated_at, sensitive credentials never enter meta, and visitors are not misattributed. Cases restore with revoked links until Manager regenerates them. No permanent purge action in MVP.
- [ ] Step 4: Run task tests; expect restoration of correct descendants and financial history retention.

### Task 10: Doctor visits and branch operational pages

**Files:** Create `app/Models/DoctorVisit.php`, `app/Policies/DoctorVisitPolicy.php`, `app/Services/VisitSummary.php`, `resources/views/pages/admin/⚡visits.blade.php`, `resources/views/pages/admin/branches/⚡show.blade.php`; modify Doctor/Branch relationships and navigation. Test: `tests/Feature/DoctorVisitsTest.php`, `tests/Feature/BranchOverviewTest.php`.

**Interfaces:** `VisitSummary::forActor(User $actor, ?int $branchId, string $month): array` returns completed_this_month, not_visited_this_month, overdue, doctor_counts, last_visit and next_visit. Visit row holds doctor_id/branch_id/responsible_user_id/visited_at/next_visit_at/comment/agreement/notes. Proposed stale threshold=90 days, configurable in center settings.

- [ ] Step 1: Add `test_monthly_visit_summary_counts_visits_not_just_doctors`, `test_pending_appointment_is_not_completed_visit`, `test_visit_permission_and_branch_are_enforced`, and `test_branch_overview_scopes_patients_users_prices_and_finance`.
- [ ] Step 2: Run `php artisan test --filter='DoctorVisitsTest|BranchOverviewTest'`; expect missing module failures.
- [ ] Step 3: Implement filterable visit CRUD with no image uploads, linked responsible users/doctors/branches, last/next visit aggregation and agreement history. Branch page tabs show its cases/patients/team/doctors/exams/prices/statistics, plus finance only under its role/date scope. Manager may grant ManageVisits to a permitted Reception user without creating a fifth role.
- [ ] Step 4: Run task tests; expect next/latest dates derived consistently and unauthorized branch records rejected.

### Task 11: Dashboard and responsive Arabic navigation

**Files:** Modify Dashboard, sidebar, `resources/css/app.css`, existing page-header/panel/status/stat components; create `resources/views/components/workflow-badge.blade.php`. Test: `tests/Feature/MvpDashboardTest.php`.

**Interfaces:** Dashboard summaries consume scoped AccessScope queries, workflow statuses, FinancialReport and VisitSummary. Branch/date selectors use the same authorization semantics as source pages; aggregate counts never reveal inaccessible branches.

- [ ] Step 1: Add `test_dashboard_counts_new_in_progress_completed_and_total`, `test_financial_and_visit_widgets_follow_permissions`, and `test_shared_patient_count_has_no_cross_branch_cases`; assert listed KPI coverage against seeded fixture counts.
- [ ] Step 2: Run `php artisan test --filter=MvpDashboardTest`; expect incomplete KPI/role behavior.
- [ ] Step 3: Implement the attached design plan: operational header and single «حالة جديدة», scoped work queue before charts, branch/exam breakdowns, users and doctor summaries, visits and finance according to role. Update nav labels to «أنواع الفحوصات»، «المستخدمون والصلاحيات»، «الحسابات»، «زيارات الأطباء»، «المحذوفات». Render simple mobile case rows and progressive disclosure; avoid exposing financially restricted data through hidden DOM or serialized properties.
- [ ] Step 4: Run DashboardTest and task tests; expect exact visible KPI totals and absence of restricted sections/data.

### Task 12: Integration checks, connection handoff, and documentation

**Files:** Modify README and `.env.example`; add `docs/google-drive-setup.md`, `docs/mvp-acceptance.md`; update current tests for four-role and new-permission semantics. No live financial/medical data is used as test fixtures.

**Interfaces:** Existing local project URL remains usable; Drive settings show connected/disconnected/failed states accurately. Production commands include migration, queue worker, scheduler and asset build without destructive reseeding.

- [ ] Step 1: Run `composer test` (Pint, PHPStan, PHPUnit) and `npm run build`; expect all checks succeed. If Google-font fetch is network-blocked, request the required network approval instead of silently changing fonts.
- [ ] Step 2: Review Arabic UI in Chrome using synthetic fixtures at 375/768/1440px: registration age/year edits, anonymous draft, file/video progress/errors, share selection and expiry, per-role nav, recovery, daily Reception reporting. Capture the final screens and correct layout/focus/RTL issues.
- [ ] Step 3: After the user connects the intended Drive account, verify one synthetic image/PDF/video upload, folder layout, private authorized retrieval, retry and revoked share. Do not upload existing patient files as a connection test.
- [ ] Step 4: Document implemented behavior, current currency/timezone and answers, remaining external setup, recoverable-delete semantics and deferred features. Mark tasks complete only on actual evidence; if Drive remains disconnected, explicitly report that live acceptance is outstanding.

## Plan self-review

- User sections 1–3 → task 3/8; 4 → task 11; 5 → tasks 1/2/5/10; 6–8 → tasks 5/6; 9–11 → task 2/4/6; 12–16 → tasks 7/8; 17 → task 10; 18–19 → task 9; 20 → phased groups and task 12.
- All five Review Focus conditions have specific regression checks in their owning tasks.
- Case pricing, payment aggregation, file storage, branch scope and visit summaries share the named interfaces above. Credentials/setup choices are not silently assumed.
- The user approved execution with «start». Implementation proceeded in this workspace. See the execution record below for actual files, consolidated tests, and outstanding external acceptance.


## Execution record — 2026-10-01

The planned interfaces were adapted to the existing Livewire architecture. Tests are consolidated in `RadiologyMvpTest.php` plus the existing feature suite. Historical red-first checklist steps were not performed as written and are not marked complete.

| Group | Actual outcome |
|---|---|
| 1–2 Identity/access | Additive migrations, four roles, permission overrides, branch/assignment scopes, policies, users UI |
| 3–4 Registration/workflow | CaseRegistration, PatientAge, CaseClinicalUpdates, unified case and patient forms, technician queue, doctor reports |
| 5–6 Prices/accounts | CasePricing, CasePayments, FinancialReport, catalog/branch prices, case finance panel, accounts and CSV |
| 7 Drive/files | DriveClient/FolderResolver/CaseStorage, OAuth controller, encrypted connection, upload sessions, Queue, private streaming; HTTP simulation passed |
| 8 Sharing | CaseSharePanel, selected ready files, patient history, current token routes and revocation |
| 9 Recovery/audit | RecordRecovery, batch-aware cascade/restore, retained bytes, audit of mutations and price snapshots |
| 10 Visits/branches | DoctorVisit and pendingFollowup scope, visits UI, branch overview |
| 11 UI/dashboard | Queue-led dashboard, Arabic navigation, mobile form and responsive CSS |
| 12 Integration | 72 tests/308 assertions, PHPStan zero errors, Pint and asset build; Chrome review; README/Drive setup/acceptance docs |

- [x] Implement core MVP locally.
- [x] Run local functional, type, style and asset checks.
- [x] Document business defaults and real-connection limitations.
- [ ] Connect the center Google account and run synthetic live Drive acceptance (requires external setup).
- [ ] Production deployment/continuous worker and scheduler (not requested in this session).

Canonical evidence and remaining setup: `docs/mvp-acceptance.md`.
