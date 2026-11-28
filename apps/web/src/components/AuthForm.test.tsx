import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api";

const { apiFetchMock, documentReloadMock, routerPushMock } = vi.hoisted(() => ({
  apiFetchMock: vi.fn(),
  documentReloadMock: vi.fn(),
  routerPushMock: vi.fn(),
}));

vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  apiFetch: apiFetchMock,
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: routerPushMock }),
}));

vi.mock("@/lib/browser-navigation", () => ({
  navigateWithDocumentReload: documentReloadMock,
}));

import { AuthForm } from "@/components/AuthForm";

describe("AuthForm", () => {
  beforeEach(() => {
    apiFetchMock.mockReset();
    documentReloadMock.mockReset();
    routerPushMock.mockReset();
    window.localStorage.clear();
  });

  afterEach(cleanup);

  it.each(["login", "register"] as const)(
    "continues an already-authenticated %s session into the application",
    async (mode) => {
      apiFetchMock.mockRejectedValue(
        new ApiError("You are already signed in.", 409),
      );
      render(<AuthForm mode={mode} />);

      if (mode === "register") {
        fireEvent.change(screen.getByLabelText("Name"), {
          target: { value: "David Oliver" },
        });
      }
      fireEvent.change(screen.getByLabelText("Email address"), {
        target: { value: "david@example.test" },
      });
      fireEvent.change(screen.getByLabelText("Password"), {
        target: { value: "Correct-Horse-7!" },
      });
      if (mode === "register") {
        fireEvent.change(screen.getByLabelText("Confirm password"), {
          target: { value: "Correct-Horse-7!" },
        });
      }

      fireEvent.submit(screen.getByRole("button", {
        name: mode === "login" ? "Sign in" : "Create account",
      }).closest("form")!);

      if (mode === "login") {
        await waitFor(() => expect(documentReloadMock).toHaveBeenCalledWith("/app"));
        expect(routerPushMock).not.toHaveBeenCalled();
      } else {
        await waitFor(() => expect(routerPushMock).toHaveBeenCalledWith("/app"));
        expect(documentReloadMock).not.toHaveBeenCalled();
      }
      expect(screen.queryByRole("alert")).toBeNull();
    },
  );

  it("reloads the authenticated dashboard after an ordinary successful sign-in", async () => {
    apiFetchMock.mockResolvedValue({ data: { user: {} } });
    render(<AuthForm mode="login" />);

    fireEvent.change(screen.getByLabelText("Email address"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "password" } });
    fireEvent.submit(screen.getByRole("button", { name: "Sign in" }).closest("form")!);

    await waitFor(() => expect(documentReloadMock).toHaveBeenCalledWith("/app"));
    expect(routerPushMock).not.toHaveBeenCalled();
  });

  it("uses platform-specific sign-in copy and returns to the allowlisted operations route", async () => {
    apiFetchMock.mockResolvedValue({ data: { user: {} } });
    render(<AuthForm context="platform" mode="login" returnTo="/app/platform/operations" />);

    expect(screen.getByRole("heading", { name: "Platform Operations" })).toBeTruthy();
    expect(screen.getByText(/OpenTelemetry health/)).toBeTruthy();
    expect(screen.getByText(/without crossing tenant boundaries/)).toBeTruthy();
    fireEvent.change(screen.getByLabelText("Email address"), { target: { value: "operator@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "password" } });
    fireEvent.submit(screen.getByRole("button", { name: "Sign in" }).closest("form")!);

    await waitFor(() => expect(documentReloadMock).toHaveBeenCalledWith("/app/platform/operations"));
    expect(routerPushMock).not.toHaveBeenCalled();
  });

  it("keeps the invitation journey through registration and email verification", async () => {
    apiFetchMock.mockResolvedValue({ data: { user: {} } });
    const returnTo = "/invitations/invite-token";
    render(<AuthForm mode="register" returnTo={returnTo} />);

    fireEvent.change(screen.getByLabelText("Name"), { target: { value: "Invited Person" } });
    fireEvent.change(screen.getByLabelText("Email address"), { target: { value: "invited@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "Correct-Horse-7!" } });
    fireEvent.change(screen.getByLabelText("Confirm password"), { target: { value: "Correct-Horse-7!" } });
    fireEvent.submit(screen.getByRole("button", { name: "Create account" }).closest("form")!);

    await waitFor(() => expect(routerPushMock).toHaveBeenCalledWith(
      "/verify-email?next=%2Finvitations%2Finvite-token",
    ));
    expect(apiFetchMock).toHaveBeenCalledWith("/api/auth/register", expect.objectContaining({
      body: expect.stringContaining('"invitation_token":"invite-token"'),
    }));
    expect(window.localStorage.getItem("dolved.postVerificationReturnTo")).toBe(returnTo);
    expect(screen.getByRole("link", { name: "Already have an account? Sign in" }).getAttribute("href"))
      .toBe("/login?next=%2Finvitations%2Finvite-token");
  });

  it("does not advertise self-registration when it is disabled", () => {
    render(<AuthForm mode="login" registrationEnabled={false} />);

    expect(screen.queryByRole("link", { name: "Create an account" })).toBeNull();
    expect(screen.getByRole("link", { name: "Forgot password?" })).toBeTruthy();
  });

  it("offers password reveal and persistent-session controls on sign in", () => {
    render(<AuthForm mode="login" />);

    const password = screen.getByLabelText("Password") as HTMLInputElement;
    expect(password.type).toBe("password");
    fireEvent.click(screen.getByRole("button", { name: "Show password" }));
    expect(password.type).toBe("text");
    expect(screen.getByRole("checkbox", { name: "Remember me on this device" })).toBeTruthy();
  });

  it("shows live password strength when choosing a new password", () => {
    render(<AuthForm mode="reset" />);

    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "Correct-Horse-7!Long" } });
    expect(screen.getByText(/Strong/)).toBeTruthy();
    expect(screen.getAllByRole("button", { name: "Show password" })).toHaveLength(2);
  });
});
