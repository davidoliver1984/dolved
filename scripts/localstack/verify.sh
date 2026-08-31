#!/bin/sh

set -eu

: "${DOCUMENT_UPLOAD_BUCKET:=rag-platform-document-uploads-local}"
: "${DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN:=http://localhost:3000}"
: "${INGESTION_QUEUE:=rag-platform-ingestion-local}"
: "${INGESTION_DLQ:=rag-platform-ingestion-dlq-local}"
: "${SQS_MAX_RECEIVE_COUNT:=3}"

awslocal s3api head-bucket \
    --bucket "$DOCUMENT_UPLOAD_BUCKET"

public_access_block="$(
    awslocal s3api get-public-access-block \
        --bucket "$DOCUMENT_UPLOAD_BUCKET" \
        --output json
)"

case "$public_access_block" in
    *'"BlockPublicAcls": true'*'"IgnorePublicAcls": true'*'"BlockPublicPolicy": true'*'"RestrictPublicBuckets": true'*)
        ;;
    *)
        printf 'Document-upload bucket is not fully private: %s\n' \
            "$public_access_block" \
            >&2
        exit 1
        ;;
esac

bucket_encryption="$(
    awslocal s3api get-bucket-encryption \
        --bucket "$DOCUMENT_UPLOAD_BUCKET" \
        --output json
)"

case "$bucket_encryption" in
    *'"SSEAlgorithm": "AES256"'*)
        ;;
    *)
        printf 'Document-upload bucket encryption is not configured: %s\n' \
            "$bucket_encryption" \
            >&2
        exit 1
        ;;
esac

cors_configuration="$(
    awslocal s3api get-bucket-cors \
        --bucket "$DOCUMENT_UPLOAD_BUCKET" \
        --output json
)"

case "$cors_configuration" in
    *"PUT"*"HEAD"*"$DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN"*)
        ;;
    *)
        printf 'Unexpected document-upload CORS configuration: %s\n' \
            "$cors_configuration" \
            >&2
        exit 1
        ;;
esac

queue_url="$(
    awslocal sqs get-queue-url \
        --queue-name "$INGESTION_QUEUE" \
        --query QueueUrl \
        --output text
)"

dlq_url="$(
    awslocal sqs get-queue-url \
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

redrive_policy="$(
    awslocal sqs get-queue-attributes \
        --queue-url "$queue_url" \
        --attribute-names RedrivePolicy \
        --query Attributes.RedrivePolicy \
        --output text
)"

case "$redrive_policy" in
    *"\"deadLetterTargetArn\": \"$dlq_arn\""*"\"maxReceiveCount\": $SQS_MAX_RECEIVE_COUNT"*)
        ;;
    *"\"deadLetterTargetArn\":\"$dlq_arn\""*"\"maxReceiveCount\":\"$SQS_MAX_RECEIVE_COUNT\""*)
        ;;
    *)
        printf 'Unexpected redrive policy: %s\n' "$redrive_policy" >&2
        exit 1
        ;;
esac

printf 'Local AWS resources verified:\n'
printf '  bucket: %s\n' "$DOCUMENT_UPLOAD_BUCKET"
printf '  bucket public access: blocked\n'
printf '  bucket encryption: AES256\n'
printf '  upload CORS origin: %s\n' "$DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN"
printf '  queue:  %s\n' "$queue_url"
printf '  dlq:    %s\n' "$dlq_url"
printf '  redrive maxReceiveCount: %s\n' "$SQS_MAX_RECEIVE_COUNT"
