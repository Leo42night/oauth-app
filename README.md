# Google OAuth PHP App - Deployment Guide
Run in WSL.

## Prerequisites
- Google Cloud Platform account
- gcloud CLI installed and configured
- A Google Cloud project created

## Step 1: Set Up Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Navigate to **APIs & Services > Credentials**
3. Click **Create Credentials > OAuth 2.0 Client ID**
4. Configure the OAuth consent screen if prompted
5. Select **Web application** as the application type
6. Add authorized redirect URIs:
   - For local testing: `http://localhost:8080/callback.php`
   - For Cloud Run: `https://YOUR-SERVICE-URL/callback.php` (add this after deployment)
7. Save the **Client ID** and **Client Secret**

## Step 2: Create Cloud SQL Instance

```bash
# Set your project ID
export PROJECT_ID=your-project-id
gcloud config set project $PROJECT_ID

# Create a Cloud SQL MySQL instance (~10 minute)
gcloud sql instances create oauth-db \
  --database-version=MYSQL_8_0 \
  --tier=db-f1-micro \
  --region=us-central1

# Set root password (> 1 min.)
gcloud sql users set-password root \
  --host=% \
  --instance=oauth-db \
  --password=YOUR_STRONG_PASSWORD

# Create application database
gcloud sql databases create oauth_app --instance=oauth-db

# Create application user (9.01 - 09.03)
gcloud sql users create app_user \
  --instance=oauth-db \
  --password=YOUR_APP_PASSWORD
```

## Step 3: Store Secrets in Secret Manager

```bash
# Enable Secret Manager API (09.03 - 09.04)
gcloud services enable secretmanager.googleapis.com

# Create secret for database password
echo -n "YOUR_APP_PASSWORD" | gcloud secrets create db-password --data-file=-

# Grant Cloud Run access to the secret (09.15-)
gcloud secrets add-iam-policy-binding db-password \
  --member="serviceAccount:PROJECT_NUMBER-compute@developer.gserviceaccount.com" \
  --role="roles/secretmanager.secretAccessor"
```

## Step 4: Prepare Your Application

Create a `.env` file for local testing (DO NOT commit this):

```bash
GOOGLE_CLIENT_ID="your-client-id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="your-client-secret"
CLOUDSQL_CONNECTION_NAME="your-project-id:us-central1:oauth-db" # ex: main-12345
GOOGLE_REDIRECT_URI="http://localhost:8080/callback.php"
DB_HOST=127.0.0.1
DB_USER="app_user"
DB_PASS="YOUR_APP_PASSWORD"
DB_NAME="oauth_app"
```
Export semua env ke terminal:
```bash
export $(grep -v '^#' .env | xargs)
# cek: echo $DB_PASS
```

## Step 5: Test Locally with Cloud SQL Proxy

```bash
# Download Cloud SQL Proxy
curl -o cloud-sql-proxy https://storage.googleapis.com/cloud-sql-connectors/cloud-sql-proxy/v2.8.0/cloud-sql-proxy.linux.amd64
chmod +x cloud-sql-proxy

# Start the proxy (in a separate terminal)
./cloud-sql-proxy --port 3306 PROJECT_ID:us-central1:oauth-db
# ./cloud-sql-proxy --port 3306 my-oauth-project-123:us-central1:oauth-db
# jika butuh credential ADC: gcloud auth application-default login

# Run PHP built-in server (09:24:15 - )
php -S localhost:8080
# proxy db dan server php harus jalan di tipe terminal yang sama (linux: wsl atau gitbash)
```

Visit `http://localhost:8080` and test the OAuth flow.

## Step 6: Deploy to Cloud Run

### Option A: Using gcloud command

```bash
# Get your Cloud SQL instance connection name
export CLOUDSQL_CONNECTION_NAME=$(gcloud sql instances describe oauth-db --format="value(connectionName)")

# Deploy to Cloud Run
gcloud run deploy oauth-app \
  --source . \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated \
  --add-cloudsql-instances $CLOUDSQL_CONNECTION_NAME \
  --set-env-vars "DB_USER=app_user,DB_NAME=oauth_app,DB_UNIX_SOCKET=/cloudsql/$CLOUDSQL_CONNECTION_NAME,GOOGLE_CLIENT_ID=YOUR_CLIENT_ID,GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET,GOOGLE_REDIRECT_URI=https://YOUR-SERVICE-URL/callback.php" \
  --set-secrets "DB_PASS=db-password:latest"
```

### Option B: Using Cloud Build (Recommended)

```bash
# Enable required APIs (09.55 - 56)
gcloud services enable cloudbuild.googleapis.com run.googleapis.com

# Submit build with substitutions (10.03 - ~05)
gcloud builds submit \
  --config cloudbuild.yaml \
  --substitutions _CLOUDSQL_INSTANCE="$CLOUDSQL_CONNECTION_NAME",_DB_USER="app_user",_DB_NAME="oauth_app",_DB_PASS_SECRET="db-password",_GOOGLE_CLIENT_ID="YOUR_CLIENT_ID",_GOOGLE_CLIENT_SECRET="YOUR_CLIENT_SECRET",_GOOGLE_REDIRECT_URI="https://YOUR-SERVICE-URL/callback.php"
# _DB_USER adalah Cloud Build substitution var
```
Periksa env sebelum run:
```bash
echo $DB_USER
echo $DB_NAME
echo $GOOGLE_CLIENT_ID
echo $GOOGLE_CLIENT_SECRET
```

## Step 7: Update OAuth Redirect URI

1. After deployment, get your Cloud Run service URL (10.23 ):
   ```bash 
   gcloud run services describe oauth-app --region us-central1 --format="value(status.url)"
   ```

2. Go back to Google Cloud Console > **APIs & Services > Credentials**
3. Edit your OAuth 2.0 Client ID
4. Add the Cloud Run URL with `/callback.php` to authorized redirect URIs:
   ```
   https://your-service-url.run.app/callback.php
   ```

## Step 8: Update Environment Variables

Update the `GOOGLE_REDIRECT_URI` environment variable:

```bash
gcloud run services update oauth-app \
  --region us-central1 \
  --update-env-vars GOOGLE_REDIRECT_URI="https://your-service-url.run.app/callback.php"
#akan update tipe service url baru: Service URL: https://oauth-app-690018681234.us-central1.run.app
```
Memastikan Redirect URI sesuai, ke:
- Google Cloud Console →
- APIs & Services →
- Credentials →
- OAuth 2.0 Client IDs → (client yang kamu pakai)
- Tambahkan service url yang baru ke Authorized redirect URIs (yang lama bisa dihapus), SAVE.

## Verification

1. Visit your Cloud Run URL
2. Click "Sign in with Google"
3. Complete the OAuth flow
4. Verify user data is stored in Cloud SQL:

```bash
# 5 min
gcloud sql connect oauth-db --user=app_user

# jika mysql CLI belum terinstall
sudo apt update && sudo apt install default-mysql-client

# In MySQL prompt:
USE oauth_app;
SELECT * FROM users;
```

## Troubleshooting

### View Logs
```bash
gcloud run services logs read oauth-app --region us-central1
```

### Common Issues

1. **Database connection failed**: Ensure Cloud SQL instance is connected and credentials are correct
2. **OAuth redirect mismatch**: Verify redirect URI matches exactly in both code and Google Console
3. **Permission denied**: Check IAM roles for the Cloud Run service account

## Cost Optimization

- Use `db-f1-micro` tier for Cloud SQL (free tier eligible)
- Cloud Run only charges when processing requests
- Enable Cloud SQL automatic backups for production

## Security Best Practices

1. Never commit credentials to version control
2. Use Secret Manager for sensitive data
3. Enable HTTPS only (Cloud Run default)
4. Regularly rotate OAuth client secrets
5. Enable Cloud SQL automatic backups
6. Use least privilege IAM roles

## Cleanup

To avoid charges, delete resources when done:

```bash
gcloud run services delete oauth-app --region us-central1
gcloud sql instances delete oauth-db
gcloud secrets delete db-password
```

## Additional Resources

- [Cloud Run Documentation](https://cloud.google.com/run/docs)
- [Cloud SQL Documentation](https://cloud.google.com/sql/docs)
- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)

## Connect Github
- Masuk ke IAM -> tambahkan permission "Secret Accessor" ke service account
- Masuk Cloud Build / Triggers -> Connect Repository
- Region: asia-southeast1
- Event: Push to a branch
- Source: Cloud Build repositories
- Add Substitution variables: pakai di cloudbuild.yaml
- Type: Cloud Build yaml
- Location: Repository