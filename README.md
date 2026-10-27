# Queue Messages Using Amazon SQS

A PHP application that generates 10,000 fictional customers, selects 50 opted-in recipients, and sends promotional emails through Mailtrap Email Sandbox using Amazon SQS.

```text
Faker → Local MariaDB → SQS → PHP Worker → Mailtrap Sandbox
                            ↓
                     Failed jobs → DLQ
```

## Requirements

- PHP 8.2+ with PDO MySQL, OpenSSL, mbstring, and SimpleXML
- Composer and AWS CLI v2
- Local MariaDB or MySQL
- AWS access for SQS, IAM, CloudWatch, and SNS
- Mailtrap Email Sandbox with at least 50 available submissions

## Setup

1. Clone the repository and install dependencies:

   ```powershell
   git clone https://github.com/kbfrrr/Queue-Messages-using-SQS.git marketing-email-lab
   cd marketing-email-lab
   composer install
   Copy-Item .env.example .env
   ```

2. Start your database server and create the database in phpMyAdmin or your SQL client:

   ```sql
   CREATE DATABASE marketing_lab
   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Configure your AWS profile:

   ```powershell
   aws configure --profile marketing-lab
   ```

4. Edit `.env` with your database connection, AWS profile, queue Region and URL, and Mailtrap SMTP settings. Keep `MAILTRAP_DELAY_SECONDS=11` unless your assigned plan requires a different rate.

   Existing terminal environment variables take precedence over `.env`. Keep AWS access keys in your AWS profile.

## AWS configuration

Create two **Standard** queues in the same Region:

| Queue | Settings |
| --- | --- |
| Main queue | 180-second visibility timeout, 4-day retention, 20-second receive wait |
| Dead-letter queue (DLQ) | 14-day retention; attach it to the main queue with maximum receives set to 3 |

Update the queue ARNs in `iam-policy.json` for your account and attach the policy using your classroom-approved credential setup.

Before publishing, create a CloudWatch dashboard for send/receive/delete counts, visible/in-flight backlog, oldest-message age, and DLQ backlog. Use **Sum** for operation counts and **Maximum** for backlog and age, with one-minute periods.

Create these alarms with **Maximum**, a one-minute period, **1 out of 1** datapoints, and missing data **not breaching**:

- DLQ visible messages: **≥ 1**
- Main queue oldest-message age: **≥ 300 seconds**

Connect both alarms to a confirmed SNS email subscription and capture the baseline.

## Run the application

Prepare the local data first. After dependencies are installed, these steps work without AWS or SMTP access:

```powershell
php init-db.php
php seed-customers.php
php prepare-campaign.php CAMP-001 PROMO15
php inspect.php
```

Expected: **10,000 customers**, including **8,000 opted in** and **2,000 opted out**, with **50 PENDING campaign requests**.

Publish while the worker is stopped:

```powershell
php enqueue.php
php inspect.php
```

Expected: **50 QUEUED requests**. Repeating publication should publish zero additional requests.

Confirm Mailtrap capacity, then start **one worker**:

```powershell
php worker.php
```

In another terminal, watch activity:

```powershell
Get-Content .\logs\events.jsonl -Tail 20 -Wait
```

Run `php inspect.php` to check progress. The baseline should finish with **50 SENT outcomes** for `CAMP-001`. Outbox rows remain `QUEUED`; processing outcomes are stored separately. Stop the worker with **Ctrl+C** when finished.

## Verification

- Repeating generation preserves existing customers; repeating preparation preserves the same 50 recipients and promo code.
- Resubmitting a completed campaign/customer pair produces `duplicate_skipped` without another email.
- Malformed requests retry and eventually enter the DLQ.
- A corrected `CAMP-RECOVERY` request for opted-out `CUST-08001` produces `SKIPPED`, followed by deletion, without sending email.
- After verified recovery, remove the malformed DLQ message and confirm the backlog clears and alarm returns to `OK`.

SQS uses at-least-once delivery. Duplicate checks reduce repeated sends but do not guarantee exactly-once delivery.

## Repository contents

`src/` contains the PHP classes. Root PHP scripts run each stage. `schema.sql` defines the three local tables, and `.env.example` lists configuration placeholders.

Keep credentials, `.env`, database exports, generated customer records, logs, and `vendor/` out of Git. Capture report evidence separately, including Mailtrap samples before older messages are removed.
