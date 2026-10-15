import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store } from '@/routes/reports';

export type ReportStep =
    | 'location'
    | 'held'
    | 'notHeldReason'
    | 'voted'
    | 'notVotedReason'
    | 'party'
    | 'resultSheet';

const stepForField: Record<string, ReportStep> = {
    polling_unit_id: 'location',
    not_held_reason: 'notHeldReason',
    not_voted_reason: 'notVotedReason',
    party_id: 'party',
    result_sheet: 'resultSheet',
};

/**
 * The report questions, their branching and the submission, shared by the form and story mode so
 * both always send the same report.
 */
export function useReportFlow() {
    const practice = usePage().props.mode === 'test';
    const step = ref<ReportStep>('location');
    const history = ref<ReportStep[]>([]);

    const form = useForm({
        polling_unit_id: null as number | null,
        election_held: null as boolean | null,
        not_held_reason: null as string | null,
        voted: null as boolean | null,
        not_voted_reason: null as string | null,
        party_id: null as number | null,
        reason_details: '',
        result_sheet: null as File | null,
        website: '',
    });

    const preparingPhoto = ref(false);

    function hasReason(reason: string | null): boolean {
        if (reason === null) {
            return false;
        }

        return reason !== 'other' || form.reason_details.trim() !== '';
    }

    const canContinue = computed(() => {
        switch (step.value) {
            case 'location':
                return form.polling_unit_id !== null;
            case 'notHeldReason':
                return hasReason(form.not_held_reason);
            case 'notVotedReason':
                return hasReason(form.not_voted_reason);
            case 'party':
                return form.party_id !== null;
            case 'resultSheet':
                return !preparingPhoto.value;
            default:
                return false;
        }
    });

    const isFinalStep = computed(() =>
        ['notHeldReason', 'resultSheet'].includes(step.value),
    );

    // Server-only errors with no field on the page: the election closed, or this VIN reported from another tab.
    const formError = computed(() => {
        const errors = form.errors as Partial<Record<string, string>>;

        return errors.election ?? errors.vin;
    });

    function goTo(next: ReportStep): void {
        history.value.push(step.value);
        step.value = next;
    }

    function back(): void {
        const previous = history.value.pop();

        if (previous) {
            step.value = previous;
        }
    }

    function answerHeld(held: boolean): void {
        form.election_held = held;
        goTo(held ? 'voted' : 'notHeldReason');
    }

    function answerVoted(voted: boolean): void {
        form.voted = voted;
        goTo(voted ? 'party' : 'notVotedReason');
    }

    function submit(): void {
        const held = form.election_held === true;
        const voted = held && form.voted === true;
        const reason = held ? form.not_voted_reason : form.not_held_reason;

        // Answers from branches the voter backed out of must not be sent.
        form.transform((data) => ({
            ...data,
            not_held_reason: held ? null : data.not_held_reason,
            voted: held ? data.voted : null,
            not_voted_reason: held && !voted ? data.not_voted_reason : null,
            party_id: voted ? data.party_id : null,
            reason_details:
                !voted && reason === 'other' ? data.reason_details : null,
            // Practice photos stay on the phone; the server saves a sample sheet instead.
            result_sheet: held && !practice ? data.result_sheet : null,
            sample_result_sheet: held && practice && data.result_sheet !== null,
        })).post(store.url(), {
            onError: (errors) => {
                const field = Object.keys(errors).find(
                    (key) => key in stepForField,
                );

                if (field) {
                    const target = stepForField[field];
                    const index = history.value.indexOf(target);

                    history.value =
                        index === -1 ? [] : history.value.slice(0, index);
                    step.value = target;
                }
            },
        });
    }

    function next(): void {
        if (!canContinue.value) {
            return;
        }

        if (step.value === 'location') {
            goTo('held');
        } else if (step.value === 'notVotedReason' || step.value === 'party') {
            goTo('resultSheet');
        } else if (isFinalStep.value) {
            submit();
        }
    }

    return {
        form,
        step,
        history,
        preparingPhoto,
        canContinue,
        isFinalStep,
        formError,
        back,
        answerHeld,
        answerVoted,
        next,
    };
}
