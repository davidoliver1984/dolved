import { Skeleton } from "@/components/ui/skeleton";

export default function KnowledgeLibraryLoading() {
  return <div aria-label="Loading knowledge library" className="grid gap-6" role="status"><div className="grid gap-3"><Skeleton className="h-4 w-36" /><Skeleton className="h-10 w-72 max-w-full" /><Skeleton className="h-5 w-[32rem] max-w-full" /></div><Skeleton className="h-64 w-full rounded-xl" /><span className="sr-only">Loading knowledge library</span></div>;
}
