import type { Crowd } from '@/game/diorama';
import type { ReportStep } from '@/lib/report-flow';

export type Scene = {
    /** Clock time shown in the scene, also drives the sky. */
    time: string;
    /** Minutes since midnight, so the sun can move across the sky. */
    minutes: number;
    /** Point in the diorama the camera settles on. */
    focus: [number, number, number];
    crowd: Crowd;
    question: string;
    narration: string;
    bubbles: { speaker: string; line: string }[];
    safety?: string;
    choices?: { yes: string; no: string };
};

/**
 * Story mode copy. Bystander lines are flavour only: they never mention parties, candidates,
 * ethnicity or religion, and no scene rewards taking a risk.
 */
export const scenes: Record<ReportStep, Scene> = {
    location: {
        time: '6:30 am',
        minutes: 390,
        focus: [-4, 0, 6],
        crowd: 'queue',
        question: 'Where is your polling unit?',
        narration:
            'Election morning. The roads are empty because of the movement restriction, and a neighbour is already sweeping the compound. Before you set out, tell us which polling unit you are going to.',
        bubbles: [
            { speaker: 'Neighbour', line: 'Have you found your PVC?' },
            { speaker: 'You', line: 'It is right here in my pocket.' },
        ],
    },
    held: {
        time: '8:00 am',
        minutes: 480,
        focus: [0, 0, 2],
        crowd: 'queue',
        question: 'Did the election hold at your polling unit?',
        narration:
            'You reach the school gate. People hold their PVCs, plastic chairs are lined up, and someone is checking the list taped to the wall.',
        bubbles: [
            { speaker: 'Woman in the queue', line: 'I came at six o’clock!' },
            { speaker: 'Man with an umbrella', line: 'Has the BVAS arrived?' },
        ],
        choices: {
            yes: 'Yes, voting is going on',
            no: 'No, nothing is happening',
        },
    },
    notHeldReason: {
        time: '11:00 am',
        minutes: 660,
        focus: [1, 0, -1],
        crowd: 'gone',
        question: 'Why did the election not hold?',
        narration:
            'The chairs are empty and the canopy moves in the wind. Tell us what happened so it is on record.',
        bubbles: [
            {
                speaker: 'Old man on a bench',
                line: 'We have waited since morning.',
            },
        ],
        safety: 'If anything feels unsafe, leave first. You can report from somewhere safe.',
    },
    voted: {
        time: '10:00 am',
        minutes: 600,
        focus: [1, 0, 3.6],
        crowd: 'queue',
        question: 'Did you vote?',
        narration:
            'The queue moves slowly. Someone sells pure water and gala by the gate, and the generator hums behind the classroom.',
        bubbles: [
            {
                speaker: 'Corps member',
                line: 'Next! Thumb on the scanner, please.',
            },
            {
                speaker: 'Mama with a baby',
                line: 'This sun is not playing today.',
            },
        ],
        choices: {
            yes: 'Yes, I voted',
            no: 'No, I could not vote',
        },
    },
    notVotedReason: {
        time: '1:00 pm',
        minutes: 780,
        focus: [4, 0, 2],
        crowd: 'queue',
        question: 'Why were you unable to vote?',
        narration:
            'That is frustrating. Tell us what stopped you, so the problem is counted.',
        bubbles: [
            {
                speaker: 'Voter beside you',
                line: 'You are not the only one today.',
            },
        ],
        safety: 'Do not argue with officials or agents. Step away and report it here.',
    },
    party: {
        time: '12:00 pm',
        minutes: 720,
        focus: [1, 0.5, -1],
        crowd: 'queue',
        question: 'Which party did you vote for?',
        narration:
            'Behind the cardboard screen it is just you and the ballot paper. Only you see this answer. It never appears on your share card.',
        bubbles: [],
    },
    resultSheet: {
        time: '5:30 pm',
        minutes: 1050,
        focus: [-4.5, 1, -2],
        crowd: 'atWall',
        question: 'Has counting finished?',
        narration:
            'Counting is done. Once the officials have filled in the result sheet (Form EC8A) and pasted it up, take a clear photo of the whole sheet.',
        bubbles: [
            {
                speaker: 'Polling agent',
                line: 'They have pasted it. Everyone can see it.',
            },
        ],
        safety: 'Only take the photo when it is safe to do so. You can also come back later with your VIN.',
    },
};
