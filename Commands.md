# Commands

## Add dummy data

```sh
php bin/console app:insert-dummy-data
```

> **Note:** This could cause errors if there are modifications in the entities. Ensure to update the files in the commands accordingly.

## Add Balance (no longer works)

```sh
php bin/console app:add-balance <email> <amount>
```

## Update bet session status

```sh
php bin/console app:update-bet-session-status
```

## Sync Wallet Balances

To synchronize all user wallet balances with their blockchain balances, use:

```sh
php bin/console app:sync-wallet-balances
```

This command will:
- Check all users with connected wallets
- Fetch their current token balance from the blockchain using Etherscan API
- Update the database if the blockchain balance differs from the stored balance

## Make User Role

To assign a specific role to a user, use the following command:

```sh
php bin/console app:make-user-role <email> --role=<role>
```

This command will add the specified role (admin, seller, author) to the user. If the user already has the role, it will show a warning message.

### Example

To assign the admin role to a user with email `admin@admin.com`, use:

```sh
php bin/console app:make-user-role admin@admin.com --role=admin
```

## Test SMTP Email

To test the SMTP email configuration and send a test email, use:

```sh
php bin/console app:test-smtp-email <recipient_email>
```

This command will send a test email using the configured SMTP settings:
- SMTP Server: smtp.gmail.com
- Port: 465
- Username: linuxattack69@gmail.com

The command will attempt to send a test email to the specified recipient and report success or failure.

### Example

```sh
php bin/console app:test-smtp-email test@example.com
```

## Run Mercure

To run the Mercure service using Docker, use the following command:

```sh
docker run -d -p 3000:80 -e JWT_KEY='s3cUr3R@nd0mStr1ngTh@tIsV3ryL0ngAndH@rdToGu3ss' -e MERCURE_PUBLISHER_JWT_KEY='eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2V4YW1wbGUuY29tIiwibWVyY3VyZSI6eyJwdWJsaXNoIjpbIioiXX0sImlhdCI6MTc0MDM4NjA2OC43MDMwNzZ9.1R9whLD9M7CnA8vaXE9yy7nL89G-B38QFDZHL_V4r88' -e MERCURE_SUBSCRIBER_JWT_KEY='eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2V4YW1wbGUuY29tIiwibWVyY3VyZSI6eyJzdWJzY3JpYmUiOlsiKiJdfSwiaWF0IjoxNzQwMzg2MDY4LjcwMzA3Nn0.Qr36anxOcMdw2zF6bwPrPnQcJHfyfUqBD5qlHApceFI' -e ALLOW_ANONYMOUS=1 -e CORS_ALLOWED_ORIGINS='*' dunglas/mercure
```

This command will start the Mercure service in detached mode, mapping port 3000 on your host to port 80 on the container, and setting the necessary JWT keys for publisher and subscriber.


