# Hybrid promotional campaign laboratory - reconstructed starter

This teaching project was reconstructed from the supplied
`PHP-SQS-Mailtrap-Laboratory.pdf`, not the instructor's original starter package.
The numbered tasks D1, S1-S2, C1-C3, E1-E2, Q1-Q2, P1-P5, W1-W2, and M1-M3
have now been implemented. Their completed locations retain task labels in comments.
The task walkthrough below describes the original exercise and expected behavior;
the deliberate unfinished-operation guards have already been removed.

Before running the real campaign, finish AWS/Mailtrap configuration and capture
the monitoring baseline in section 6. Offline mocked checks do not replace the
required real SQS, Mailtrap, CloudWatch, and incident-recovery evidence.

Creating this project does not initialize a database, provision AWS resources, or
send email. PHP syntax checks alone do not prove a TODO is implemented correctly.

## 1. Requirements and dependencies

- PHP 8.2+ with PDO MySQL, OpenSSL, mbstring, and SimpleXML. cURL is recommended.
- Composer and Git.
- Local MariaDB (the selected XAMPP database) or the instructor's approved MySQL version.
- phpMyAdmin, Workbench, or the MySQL command-line client.
- For the online stages: AWS CLI v2, classroom-approved AWS permissions, and
  Mailtrap Email Sandbox credentials with at least 50 available submissions.

Use PHP CLI, not browser URLs. Apache is not needed for the PHP entry scripts.
Keep the MariaDB/MySQL server running. The `config.php` file does not create any
cloud clients; only the online entry scripts request those dependencies.

```powershell
Set-Location C:\xampp\htdocs\marketing-email-lab
composer install
composer dump-autoload
php --version
php -r 'var_export(PDO::getAvailableDrivers());'
```

Commit `composer.lock` after dependency installation so the same package versions
are used on another computer. Do not commit `vendor/`.

## 2. Configure the local database

In phpMyAdmin or your database client, create the database separately:

```sql
CREATE DATABASE marketing_lab
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edit `.env` in the project root with your database settings. The local file is
ignored by Git; `.env.example` is a shareable template. For a new copy of the
project, copy `.env.example` to `.env` before entering your settings.

Alternatively, set environment variables in each PowerShell terminal running scripts:

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "3306"
$env:DB_NAME = "marketing_lab"
$env:DB_USER = "YOUR_LOCAL_MYSQL_USER"
$env:DB_PASSWORD = "YOUR_LOCAL_MYSQL_PASSWORD"
```

For an unchanged local XAMPP setup, the account may be `root` with an empty
password. Use your actual account configuration. Do not put real credentials in
source files, screenshots, or the repository. `config.php` loads the optional
`.env` file using phpdotenv for these CLI scripts and the AWS SDK. Existing terminal
environment variables take precedence. If you previously set a variable in
PowerShell, remove it (for example, `Remove-Item Env:DB_USER`) or open a fresh
terminal to use the corresponding `.env` value. Quote values containing spaces
or `#`. The `.htaccess` file blocks HTTP access to `.env` files in XAMPP.

### Complete TODO D1 before initializing

Use PDF pages 5-7 for exact types and nullability. The required fields are:

| Table | Fields |
| --- | --- |
| customers | customer_id, full_name, email, marketing_opt_in, created_at |
| marketing_outbox | request_id, campaign_id, customer_id, promo_code, subject, body, status, created_at, queued_at, sqs_message_id |
| sent_marketing_emails | campaign_id, customer_id, request_id, promo_code, recipient_email, subject, status, provider, message_id, reason, processed_at, sent_at |

- Use InnoDB and utf8mb4, with required fields marked NOT NULL.
- `customers`: primary key customer_id; unique email; consent only 0/1, default 0.
- `marketing_outbox`: primary key request_id; customer foreign key;
  UNIQUE(campaign_id, customer_id); status PENDING/QUEUED, default PENDING.
- History: PRIMARY KEY(campaign_id, customer_id); status SENT/SKIPPED/FAILED.
  Do not enforce a customer foreign key: unknown customers must be recordable.
- All timestamps use UTC. The Database class sets the connection timezone.
- Put only table definitions in `schema.sql`, with customers created first.
- Do not include CREATE DATABASE, INSERT statements, data exports, or DROP statements.

`init-db.php` refuses an empty or incomplete schema before connecting to MariaDB.
The supplied schema reader supports simple semicolon-delimited table definitions,
not stored procedures or SQL strings containing semicolons. DDL is not atomic in
MySQL/MariaDB; inspect your definitions before running initialization.

After D1 is complete:

```powershell
php init-db.php
```

Inspect table structures and keys and save evidence for your report.

## 3. Offline exercise: customers and selection

Install dependencies while online first. Then disconnect from the internet to
demonstrate that generation, selection, and inspection do not use AWS or SMTP.

### S1-S2: CustomerSeeder

- S1: Generate a name from the injected Faker Generator.
- S2: Assign consent 1 to customers 1-8000, and 0 to customers 8001-10000.
- Replace both null expressions, then remove the method's deliberate guard.
- Preserve the provided prepared insertion, transaction, identifiers, and
  example.com addresses. Rerunning must preserve existing records.

```powershell
php seed-customers.php
php inspect.php
php seed-customers.php
```

Expected: 10,000 records, 8,000 opted in and 2,000 opted out; second run adds zero.
The entry script uses Faker seed 2026 for repeatability within the same version.

### C1-C3: CampaignPreparer

- C3: Before selection, count existing rows for the requested campaign. If there
  are exactly 50, commit and return 0 unchanged. Reject a nonzero partial count.
- C2: Select 50 distinct random opted-in rows, returning customer_id in each row.
  MySQL/MariaDB RAND() with LIMIT is acceptable for this lab.
- C1: Execute the supplied seven-parameter insertion in the documented order.
- Remove each corresponding deliberate exception only after implementing it.
- Keep the transaction, persistent 32-character hexadecimal request IDs, and
  shared promo code. Do not regenerate recipients or promo codes during retry.
- Use one preparation process at a time.

```powershell
php prepare-campaign.php CAMP-001 PROMO15
php inspect.php
php prepare-campaign.php CAMP-001 PROMO15
```

Verification queries:

```sql
SELECT COUNT(*) AS total FROM customers;
SELECT marketing_opt_in, COUNT(*) AS total
FROM customers GROUP BY marketing_opt_in;

SELECT COUNT(*) AS requests, COUNT(DISTINCT customer_id) AS recipients
FROM marketing_outbox WHERE campaign_id = 'CAMP-001';

SELECT COUNT(*) AS ineligible
FROM marketing_outbox o JOIN customers c ON c.customer_id = o.customer_id
WHERE o.campaign_id = 'CAMP-001' AND c.marketing_opt_in <> 1;

SELECT customer_id, request_id, promo_code
FROM marketing_outbox WHERE campaign_id = 'CAMP-001' ORDER BY customer_id;
```

Expected: 50 PENDING requests, 50 distinct recipients, zero ineligible recipients,
unchanged selection/request IDs/promo code after repeating, and empty history.

## 4. Online setup: SQS and credentials

Use one approved Region and a unique student suffix. Each queue pair must map to
one local database. Keep customer names and email addresses out of SQS payloads.

1. Create Standard `MarketingEmailDLQ-STUDENT_ID`, retention 14 days.
2. Create Standard `MarketingEmailQueue-STUDENT_ID`, retention 4 days, visibility
   timeout 180 seconds, and receive wait 20 seconds.
3. Attach the DLQ with maximum receives 3. Keep access restricted and the
   handout's default encryption configuration.
4. Replace REGION, ACCOUNT_ID, and STUDENT_ID in `iam-policy.json`.
5. If classroom rules permit, attach that policy directly to the lab IAM user
   with console access disabled. It permits only the five specified actions on
   the two queue ARNs. Do not attach administrative/full-SQS policies.
6. Use the approved setup identity for resource creation, CloudWatch, and SNS.
   If IAM users/access keys are prohibited, obtain the classroom credential method.

For the handout's named-profile method:

```powershell
aws configure --profile marketing-lab
$env:AWS_PROFILE = "marketing-lab"
$env:AWS_REGION = "YOUR_REGION"
$env:SQS_QUEUE_URL = "YOUR_MAIN_QUEUE_URL"
aws sts get-caller-identity
aws sqs get-queue-attributes --queue-url $env:SQS_QUEUE_URL --attribute-names QueueArn
```

The PHP SDK uses its default credential chain. Do not hard-code access keys.
In a terminal where `aws` is not on PATH, the installed current-user executable
can be invoked as `& "$env:LOCALAPPDATA\Programs\Amazon\AWSCLIV2\aws.exe"`.

## 5. Mailtrap and online TODOs

Use Mailtrap **Email Sandbox**, not its production email-sending product.
Confirm submission quota, retained-message capacity, and rate limits in your
assigned sandbox. Monthly allowance and retained-message capacity are different.

Fill in the matching keys in `.env`, or use terminal variables:

```powershell
$env:MAILTRAP_HOST = "sandbox.smtp.mailtrap.io"
$env:MAILTRAP_PORT = "2525"
$env:MAILTRAP_USERNAME = "YOUR_SANDBOX_USERNAME"
$env:MAILTRAP_PASSWORD = "YOUR_SANDBOX_PASSWORD"
$env:MAIL_FROM_ADDRESS = "marketing@example.com"
$env:MAILTRAP_DELAY_SECONDS = "11"
```

Use your sandbox's actual STARTTLS-compatible endpoint/port. Retain TLS
certificate verification. Only reduce the delay if the assigned plan permits it;
coordinate aggregate rates when sharing a sandbox. An unused 50-submission
allowance covers the baseline only, so do not spend it on extra successful tests.

| Task | File | What to implement |
| --- | --- | --- |
| E1-E2 | EmailSender | Add recipient and personalized plain-text subject/body with persisted promoCode |
| Q1-Q2 | QueuePublisher | Send JSON with JSON_THROW_ON_ERROR; update QUEUED only after acknowledgment |
| M1 | QueuePublisher | Log queued after the SQS acknowledgment and successful local update |
| P1 | MarketingProcessor | Return for terminal history and log duplicate_skipped |
| P2 | MarketingProcessor | Prepared customer lookup by customerId |
| P3 | MarketingProcessor | Save SKIPPED with reason for unknown/opted-out customers; return normally |
| P4-P5 | MarketingProcessor | Send, then save SENT with recipient, provider, MIME Message-ID and UTC sent_at |
| M2 | MarketingProcessor | Log processing_started; log sent/skipped only after the corresponding outcome save |
| W1-W2 | QueueWorker | Invoke processor, then delete with current ReceiptHandle |
| M3 | QueueWorker | Set delete phase before acknowledgment; log deleted only after deletion succeeds |

Complete all tasks before removing the `QueueWorker::run()` guard. Keep ordinary
validation, FAILED-save/rethrow behavior, retry handling, and logging error phases.
The publisher/sender also have method-level guards; remove them after their TODOs
are complete. Do not remove a guard just to make a script run.

### Completed helper interfaces

- `Database::connect(): PDO` opens the local UTC connection.
- `Settings::queue()` creates an SQS client only when called.
- `Settings::smtp()` reads SMTP variables only when called.
- `LocalDatabaseTools::readSchema(path)` validates the table-only schema.
- `LocalDatabaseTools::initialize(statements)` executes those definitions.
- `LocalDatabaseTools::inspect()` returns counts and a small customer sample.
- `EmailHistory::isComplete(campaignId, customerId)` recognizes SENT/SKIPPED.
- `EmailHistory::save(job, status, details)` upserts the latest pair outcome.
  Details keys: recipient_email, provider, message_id, reason, sent_at.
- `ActivityLogger::record(event, metadata)` appends UTC JSON to logs/events.jsonl.
  Allowed metadata: requestId, campaignId, customerId, messageId, phase, status,
  receiveCount, errorType. Raw payloads and exception messages are excluded.

The processor validates required strings and field lengths before history writes.
The worker requests one message at a time and uses a 180-second visibility timeout.
Keep one worker, bound SMTP timeouts, and do not hold a database transaction over
SMTP. SQS Standard is at-least-once: terminal history suppresses sequential
duplicates, but a crash after SMTP acceptance and before history storage can still
cause a repeated send. This is not an exactly-once system.

## 6. Monitoring before publication

Run PHP syntax checks on every edited file:

```powershell
Get-ChildItem .\src -Filter *.php | ForEach-Object { php -l $_.FullName }
Get-ChildItem . -Filter *.php | ForEach-Object { php -l $_.FullName }
composer validate --strict
```

The logger creates its directory on first use. After a log exists, follow it with:

```powershell
Get-Content .\logs\events.jsonl -Tail 20 -Wait
```

Before enqueueing, capture a CloudWatch dashboard baseline. Use one-minute periods:

- Main queue NumberOfMessagesSent/Received/Deleted: Sum.
- Main queue ApproximateNumberOfMessagesVisible/NotVisible: Maximum.
- Main queue ApproximateAgeOfOldestMessage: Maximum, separate age widget.
- DLQ ApproximateNumberOfMessagesVisible: Maximum.

Create `MarketingLab-DLQ-STUDENT_ID`: DLQ visible >= 1, Maximum, one one-minute
evaluation period; missing data not breaching. Create `MarketingLab-Backlog-STUDENT_ID`:
main oldest age >= 300 seconds with the same evaluation/missing-data settings.
Configure and confirm approved SNS email notification, or document restrictions.
Metrics are approximate and delayed; use local history for campaign success totals.
Automatic DLQ arrivals are not represented by DLQ NumberOfMessagesSent.

## 7. Baseline execution after all required TODOs are complete

Keep the worker stopped initially. Confirm 50 PENDING rows and no unrelated queue jobs.

```powershell
php enqueue.php
php inspect.php
php enqueue.php
```

Expected: 50 QUEUED rows, queued events, and visible backlog. Repeat publication
must not republish QUEUED rows. Preserve one request ID and its SQS MessageId.

Then start a single worker (set DB/AWS/SMTP variables in that terminal too):

```powershell
php worker.php
```

Trace queued -> received -> processing_started -> sent -> deleted. Save Mailtrap
samples while retained. SQS MessageId and MIME Message-ID are different values.
Keep baseline consent unchanged to obtain 50 SENT outcomes for CAMP-001.
Outbox rows remain QUEUED; processing outcomes are stored separately in history.
After completion, resubmit one completed payload and verify duplicate_skipped
and deletion without another SMTP submission. Stop the worker with Ctrl+C.

## 8. Controlled failure and recovery

With the worker running after baseline completion, submit this main-queue body:

```json
{"campaignId":"CAMP-BROKEN","subject":"Test","body":"Missing IDs"}
```

Capture processing_failed, visibility-timeout retries, DLQ arrival, and the alarm.
Do not repeatedly poll the main queue; console receives affect visibility/counts.
Malformed jobs may have no history row because identifiers are missing.

Do not redrive the malformed payload unchanged. Generate one new request ID:

```powershell
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

Submit a corrected diagnostic job to the main queue:

```json
{
    "requestId": "REPLACE_WITH_GENERATED_HEX_ID",
    "campaignId": "CAMP-RECOVERY",
    "customerId": "CUST-08001",
    "promoCode": "PROMO15",
    "subject": "Recovery diagnostic",
    "body": "Controlled consent-check recovery request."
}
```

Expected: SKIPPED for the opted-out customer, then deleted, with no SMTP send.
After verified recovery, delete the original malformed DLQ message and capture
eventual zero DLQ backlog and alarm OK. Keep this result separate from CAMP-001.
Record detection, identifiers, symptoms/impact, root cause, correction, recovery,
and prevention in the incident report.

## 9. Submission checklist

Create the Google Doc requested in the handout. Include architecture, class
responsibilities and TODO completion, constructor injection, schema evidence,
10,000/8,000/2,000 counts, preserved 50-recipient selection, IAM/SQS configuration,
publishing/processing traces, Mailtrap samples, monitoring, and incident recovery.

Use a test table with action, expected result, actual result, and screenshot
reference. Cover offline/repeat generation, offline/repeat selection, repeat
publication, 50 SENT baseline, sequential duplicate, malformed-message DLQ, and
opted-out recovery. Add numbered screenshots, captions, a 150-200-word reflection,
and the GitHub repository URL. Verify instructor access to both links.

Publish completed code, composer.json/lock, table-only schema.sql, .gitignore,
the policy template, and this README to the
[Queue-Messages-using-SQS repository](https://github.com/kbfrrr/Queue-Messages-using-SQS).
Do not publish credentials, generated records, database dumps, logs, or vendor.
The ignore patterns are a starting point: manually inspect every staged file.

```powershell
git status --short
git diff --cached
```

Do not claim a test passed until you have executed it and captured its evidence.
