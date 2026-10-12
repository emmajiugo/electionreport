<template>
    <section
        role="note"
        aria-labelledby="not-official-heading"
        class="flex gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100"
    >
        <svg
            viewBox="0 0 24 24"
            class="mt-0.5 size-5 shrink-0"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="10" />
            <path d="M12 16v-4M12 8h.01" />
        </svg>
        <div class="flex flex-col gap-1 text-sm">
            <h2 id="not-official-heading" class="font-semibold">
                These are not official INEC results
            </h2>
            <p>
                ElectionReport shows what voters tell us happened at their own
                polling units and who they say they voted for. INEC has not
                checked these reports, and they are not the declared result.
            </p>
            <p>
                Use them to compare what voters report with the results INEC
                announces. Where the two differ widely, that is worth a closer
                look.
            </p>
        </div>
    </section>
</template>
