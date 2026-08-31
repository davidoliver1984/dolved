#!/bin/sh

set -eu

: "${AWS_DEFAULT_REGION:=us-east-1}"
: "${DOCUMENT_UPLOAD_BUCKET:=rag-platform-document-uploads-local}"
: "${DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN:=http://localhost:3000}"
: "${INGESTION_QUEUE:=rag-platform-ingestion-local}"
: "${INGESTION_DLQ:=rag-platform-ingestion-dlq-local}"
: "${SQS_MAX_RECEIVE_COUNT:=3}"

export AWS_DEFAULT_REGION

printf 'Provisioning LocalStack resources in %s\n' "$AWS_DEFAULT_REGION"

if ! awslocal s3api head-bucket --bucket "$DOCUMENT_UPLOAD_BUCKET" >/dev/null 2>&1; then
    if [ "$AWS_DEFAULT_REGION" = "us-east-1" ]; then
        awslocal s3api create-bucket \
            --bucket "$DOCUMENT_UPLOAD_BUCKET" \
            >/dev/null
    else
        awslocal s3api create-bucket \
            --bucket "$DOCUMENT_UPLOAD_BUCKET" \
            --create-bucket-configuration \
                "LocationConstraint=$AWS_DEFAULT_REGION" \
            >/dev/null
    fi
fi

awslocal s3api put-public-access-block \
    --bucket "$DOCUMENT_UPLOAD_BUCKET" \
    --public-access-block-configuration \
        'BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true'

bucket_encryption_file="/tmp/rag-platform-document-upload-encryption.json"
printf '%s\n' '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"},"BucketKeyEnabled":false}]}' \
    > "$bucket_encryption_file"

awslocal s3api put-bucket-encryption \
    --bucket "$DOCUMENT_UPLOAD_BUCKET" \
    --server-side-encryption-configuration "file://$bucket_encryption_file"

awslocal s3api put-bucket-versioning \
    --bucket "$DOCUMENT_UPLOAD_BUCKET" \
    --versioning-configuration Status=Enabled

rm -f "$bucket_encryption_file"

cors_configuration_file="/tmp/rag-platform-document-upload-cors.json"

printf '{"CORSRules":[{"AllowedHeaders":["content-type"],"AllowedMethods":["PUT","HEAD"],"AllowedOrigins":["%s"],"ExposeHeaders":["ETag"],"MaxAgeSeconds":300}]}\n' \
    "$DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN" \
    > "$cors_configuration_file"

awslocal s3api put-bucket-cors \
    --bucket "$DOCUMENT_UPLOAD_BUCKET" \
    --cors-configuration "file://$cors_configuration_file"

rm -f "$cors_configuration_file"

dlq_url="$(
    awslocal sqs create-queue \
        --queue-name "$INGESTION_DLQ" \
        --query QueueUrl \
        --output text
)"

dlq_arn="$(
    awslocal sqs get-queue-attributes \
        --queue-url "$dlq_url" \
        --attribute-names QueueArn \
        --query Attributes.QueueArn \
        --output text
)"

queue_url="$(
    awslocal sqs create-queue \
        --queue-name "$INGESTION_QUEUE" \
        --query QueueUrl \
        --output text
)"

redrive_attributes_file="/tmp/rag-platform-redrive-attributes.json"

printf '{"RedrivePolicy":"{\\"deadLetterTargetArn\\":\\"%s\\",\\"maxReceiveCount\\":\\"%s\\"}"}\n' \
    "$dlq_arn" \
    "$SQS_MAX_RECEIVE_COUNT" \
    > "$redrive_attributes_file"

awslocal sqs set-queue-attributes \
    --queue-url "$queue_url" \
    --attributes "file://$redrive_attributes_file"

rm -f "$redrive_attributes_file"

printf 'LocalStack resources are ready:\n'
printf '  bucket: %s\n' "$DOCUMENT_UPLOAD_BUCKET"
printf '  queue:  %s\n' "$INGESTION_QUEUE"
printf '  dlq:    %s\n' "$INGESTION_DLQ"
