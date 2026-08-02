import { z } from "zod";

const url = z
  .string()
  .url()
  .regex(/^https?:\/\//i, "Expected an HTTP or HTTPS URL")
  .transform((value) => value.replace(/\/$/, ""));

const clientEnvironmentSchema = z.object({
  NEXT_PUBLIC_API_URL: url.default("http://localhost:8000"),
});

const serverEnvironmentSchema = z.object({
  API_INTERNAL_URL: url.default("http://localhost:8000"),
  FRONTEND_URL: url.default("http://localhost:3000"),
});

type ClientEnvironmentInput = {
  NEXT_PUBLIC_API_URL?: string;
};

type ServerEnvironmentInput = {
  API_INTERNAL_URL?: string;
  FRONTEND_URL?: string;
};

export function parseClientEnvironment(input: ClientEnvironmentInput) {
  return clientEnvironmentSchema.parse(input);
}

export function parseServerEnvironment(input: ServerEnvironmentInput) {
  return serverEnvironmentSchema.parse(input);
}
