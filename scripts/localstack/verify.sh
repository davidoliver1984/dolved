#!/bin/sh

set -eu

: "${DOCUMENT_UPLOAD_BUCKET:=rag-platform-document-uploads-local}"
: "${DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN:=http://localhost:3000}"
: "${INGESTION_QUEUE:=rag-platform-ingestion-local}"
: "${INGESTION_DLQ:=rag-platform-ingestion-dlq-local}"
: "${SQS_MAX_RECEIVE_COUNT:=3}"

awslocal s3api head-bucket \
    --bucket "$DOCUMENT_UPLOAD_BUCKET"

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
printf '  upload CORS origin: %s\n' "$DOCUMENT_UPLOAD_CORS_ALLOWED_ORIGIN"
printf '  queue:  %s\n' "$queue_url"
printf '  dlq:    %s\n' "$dlq_url"
printf '  redrive maxReceiveCount: %s\n' "$SQS_MAX_RECEIVE_COUNT"
