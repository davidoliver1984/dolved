import { InvitationAcceptance } from "@/components/InvitationAcceptance";

export default async function InvitationPage({
  params,
}: Readonly<{ params: Promise<{ token: string }> }>) {
  const { token } = await params;

  return <InvitationAcceptance token={token} />;
}
