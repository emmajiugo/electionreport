import {
    BoxGeometry,
    BufferGeometry,
    CapsuleGeometry,
    Color,
    CylinderGeometry,
    DirectionalLight,
    Group,
    HemisphereLight,
    Mesh,
    MeshLambertMaterial,
    NoToneMapping,
    PerspectiveCamera,
    PlaneGeometry,
    Scene,
    SphereGeometry,
    Vector3,
    WebGLRenderer,
} from 'three';

export type Point = [number, number, number];

/** Where the people are: waiting in the queue, gone home, or crowding the posted result sheet. */
export type Crowd = 'queue' | 'gone' | 'atWall';

export type Diorama = {
    setTime: (minutes: number) => void;
    setCrowd: (crowd: Crowd) => void;
    setFocus: (focus: Point) => void;
    resize: (width: number, height: number) => void;
    setRunning: (running: boolean) => void;
    dispose: () => void;
};

const sunrise = 6 * 60;
const sunset = 19 * 60;
const dawn = new Color('#f6c49a');
const noon = new Color('#9fd3f2');
const dusk = new Color('#e98a5c');
const cameraOffset = new Vector3(20, 17, 20);

// Clothing stays in neutral tones so no figure reads as wearing a party's colours.
const shirts = [
    '#8d6e63',
    '#c49a45',
    '#6d7b8d',
    '#a1887f',
    '#5f8a8b',
    '#b0a48a',
];
const skins = ['#5b3a29', '#7a4b33', '#4a2f22', '#8a5a3c'];

/**
 * The polling unit diorama, built from simple shapes so it needs no model files. Imports are named
 * so the bundle only carries the parts of three.js used here.
 */
export function createDiorama(
    canvas: HTMLCanvasElement,
    { reducedMotion }: { reducedMotion: boolean },
): Diorama {
    const renderer = new WebGLRenderer({
        canvas,
        antialias: true,
        powerPreference: 'low-power',
    });
    // Flat colours read better without filmic tone mapping, and 1.5x is sharp enough on a phone.
    renderer.toneMapping = NoToneMapping;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));

    const scene = new Scene();
    const camera = new PerspectiveCamera(30, 1, 0.1, 200);

    const materials = new Map<string, MeshLambertMaterial>();
    const material = (
        color: string,
        options: { opacity?: number; emissive?: string } = {},
    ): MeshLambertMaterial => {
        const key = `${color}${options.opacity ?? ''}${options.emissive ?? ''}`;
        let found = materials.get(key);

        if (!found) {
            found = new MeshLambertMaterial({
                color,
                transparent: options.opacity !== undefined,
                opacity: options.opacity ?? 1,
                emissive: options.emissive ?? '#000000',
            });
            materials.set(key, found);
        }

        return found;
    };

    const mesh = (
        geometry: BufferGeometry,
        color: string,
        position: Point,
        options?: { opacity?: number; emissive?: string },
    ): Mesh => {
        const created = new Mesh(geometry, material(color, options));
        created.position.set(...position);

        return created;
    };

    const box = (
        size: Point,
        color: string,
        position: Point,
        options?: { opacity?: number; emissive?: string },
    ): Mesh => mesh(new BoxGeometry(...size), color, position, options);

    const group = (position: Point, ...children: Mesh[]): Group => {
        const created = new Group();
        created.position.set(...position);
        created.add(...children);

        return created;
    };

    const hemisphere = new HemisphereLight('#ffffff', '#8a6a45', 2);
    const sun = new DirectionalLight('#fff1d6', 2);
    scene.add(hemisphere, sun);

    // Ground and the empty road.
    const ground = mesh(new PlaneGeometry(30, 30), '#c9a26b', [0, 0, 0]);
    ground.rotation.x = -Math.PI / 2;
    const road = mesh(new PlaneGeometry(30, 4), '#4b4b4f', [0, 0.01, 7.5]);
    road.rotation.x = -Math.PI / 2;
    scene.add(ground, road);

    // School block used as the polling unit.
    scene.add(
        box([11, 3, 4], '#efe6d2', [-3, 1.5, -5]),
        box([11.6, 0.3, 4.6], '#8c5a3c', [-3, 3.15, -5]),
    );

    // Canopy over the voting table, with the transparent ballot box and BVAS.
    const pole = new CylinderGeometry(0.05, 0.05, 2.6, 6);
    scene.add(
        group(
            [1, 0, -1],
            ...(
                [
                    [-2, -1.4],
                    [2, -1.4],
                    [-2, 1.4],
                    [2, 1.4],
                ] as const
            ).map(([x, z]) => mesh(pole, '#9e9e9e', [x, 1.3, z])),
            box([4.4, 0.12, 3.2], '#f4f4f0', [0, 2.65, 0]),
            box([2.4, 0.08, 1], '#7b5a3a', [0, 0.75, 0]),
            box([0.6, 0.55, 0.5], '#d8eef5', [-0.5, 1.05, 0], {
                opacity: 0.45,
            }),
            box([0.25, 0.08, 0.35], '#222222', [0.6, 0.83, 0]),
        ),
    );

    // White plastic chairs.
    const seat = new BoxGeometry(0.5, 0.06, 0.5);
    const backrest = new BoxGeometry(0.5, 0.6, 0.06);
    const legs = new BoxGeometry(0.42, 0.44, 0.42);

    for (const x of [-2.5, -1.6, -0.7, 0.2, 1.1, 2.0]) {
        scene.add(
            group(
                [x, 0, 2.2],
                mesh(seat, '#f7f7f7', [0, 0.45, 0]),
                mesh(backrest, '#f7f7f7', [0, 0.75, -0.22]),
                mesh(legs, '#e6e6e6', [0, 0.22, 0]),
            ),
        );
    }

    // The posted result sheet (Form EC8A): plain ruled lines, no figures or party names.
    const sheetLine = new BoxGeometry(0.8, 0.025, 0.01);
    const resultSheet = group(
        // Hung high so the crowd in front of it doesn't hide it from the camera.
        [-4.5, 2.0, -2.98],
        box([1.0, 1.3, 0.01], '#fbfbf7', [0, 0, 0]),
        box([0.8, 0.08, 0.01], '#3d3d3d', [0, 0.5, 0.005]),
        ...[0.25, 0.12, -0.01, -0.14, -0.27, -0.4].map((y) =>
            mesh(sheetLine, '#9a9a9a', [0, y, 0.005]),
        ),
    );
    resultSheet.visible = false;
    scene.add(resultSheet);

    // The people: they queue, go home, or crowd round the result sheet as the story moves on.
    const trousers = new CylinderGeometry(0.12, 0.12, 0.8, 6);
    const body = new CapsuleGeometry(0.2, 0.4, 2, 6);
    const head = new SphereGeometry(0.16, 8, 6);
    const crowdSpots: Record<Crowd, (index: number) => Point> = {
        queue: (index) => [-1.2 + index * 0.9, 0, 3.6],
        // Off to the left: a straight walk there clears the chairs, the suya stand and the danfo.
        gone: (index) => [
            -11.5 - (index % 3) * 1.1,
            0,
            4.4 + Math.floor(index / 3) * 0.8,
        ],
        // Beside the canopy, which would hide the sheet from the camera.
        atWall: (index) => [
            -4.5 + (index - 2.5) * 0.7,
            0,
            -0.4 - Math.abs(index - 2.5) * 0.3,
        ],
    };
    // Walking between the queue and the wall goes round the end of the chairs, not through them.
    const aroundTheChairs = (index: number): Vector3 =>
        new Vector3(-3.4 - index * 0.3, 0, 3.2);
    const people = shirts.map((shirt, index) => {
        const person = group(
            crowdSpots.queue(index),
            mesh(trousers, '#3e3a36', [0, 0.4, 0]),
            mesh(body, shirt, [0, 1.1, 0]),
            mesh(head, skins[index % skins.length], [0, 1.58, 0]),
        );
        scene.add(person);

        return { person, phase: index * 1.3, route: [] as Vector3[] };
    });

    // Parked danfo: the roads are closed on election day.
    const wheel = new CylinderGeometry(0.32, 0.32, 0.2, 10);
    const wheels = (
        [
            [-1.3, -0.9],
            [1.3, -0.9],
            [-1.3, 0.9],
            [1.3, 0.9],
        ] as const
    ).map(([x, z]) => {
        const created = mesh(wheel, '#1a1a1a', [x, 0.32, z]);
        created.rotation.x = Math.PI / 2;

        return created;
    });
    scene.add(
        group(
            [-7, 0, 7.6],
            box([4, 1.5, 1.8], '#f2c21b', [0, 1, 0]),
            box([3.6, 0.45, 1.82], '#2c3440', [0, 1.35, 0]),
            box([4.02, 0.12, 1.84], '#111111', [0, 0.85, 0]),
            ...wheels,
        ),
    );

    // Generator humming beside the school.
    const generator = box([0.9, 0.7, 0.6], '#5d5f63', [6.5, 0.35, -3.5]);
    scene.add(generator);

    // Suya stand with smoke.
    const puff = new SphereGeometry(1, 6, 4);
    // Start the puffs small and staggered so the stand looks right even when nothing animates.
    const smoke = [0, 1, 2].map((index) => {
        const cloud = mesh(puff, '#dcdcdc', [0, 1.2 + index * 0.5, 0], {
            opacity: 0.5,
        });
        cloud.scale.setScalar(0.15 + index * 0.1);

        return cloud;
    });
    scene.add(
        group(
            [7, 0, 4],
            box([1.2, 1, 0.7], '#6b4a2f', [0, 0.5, 0]),
            box([1, 0.08, 0.5], '#d2461e', [0, 1.05, 0], {
                emissive: '#7a1a00',
            }),
            ...smoke,
        ),
    );

    const lookAt = new Vector3();
    const target = new Vector3();
    const walkTo = new Vector3();
    const walkSpeed = 2.5;
    let currentCrowd: Crowd = 'queue';
    let hasFocus = false;
    let running = false;
    let last = 0;

    const placeCamera = (): void => {
        camera.position.copy(lookAt).add(cameraOffset);
        camera.lookAt(lookAt);
    };

    const render = (): void => renderer.render(scene, camera);

    const tick = (time: number): void => {
        const elapsed = time / 1000;
        const delta = last === 0 ? 0 : Math.min(0.1, elapsed - last);
        last = elapsed;

        for (const { person, phase, route } of people) {
            const next = route.at(0);

            if (next) {
                const toNext = walkTo.set(
                    next.x - person.position.x,
                    0,
                    next.z - person.position.z,
                );
                const distance = toNext.length();
                const step = walkSpeed * delta;

                if (distance <= step) {
                    person.position.x = next.x;
                    person.position.z = next.z;
                    route.shift();
                } else {
                    person.position.addScaledVector(toNext, step / distance);
                    person.rotation.y = Math.atan2(toNext.x, toNext.z);
                }
            }

            const walking = route.length > 0;

            if (!walking) {
                person.rotation.y = Math.sin(elapsed * 0.4 + phase) * 0.35;
            }

            // Once someone reaches the edge on their way home, they leave the scene.
            person.visible = walking || currentCrowd !== 'gone';

            // A bigger, quicker bob while walking reads as footsteps.

            person.position.y = walking
                ? Math.abs(Math.sin(elapsed * 9 + phase)) * 0.08
                : Math.abs(Math.sin(elapsed * 1.6 + phase)) * 0.04;
        }

        generator.position.x = 6.5 + Math.sin(elapsed * 60) * 0.01;

        smoke.forEach((cloud, index) => {
            const rise = (elapsed * 0.5 + index / 3) % 1;
            cloud.position.y = 1.2 + rise * 1.6;
            cloud.scale.setScalar(0.15 + rise * 0.35);
        });

        // Ease the camera towards the current scene's focus point instead of cutting.
        lookAt.lerp(target, Math.min(1, delta * 2));
        placeCamera();
        render();
    };

    // With reduced motion the scene is drawn only when something changes.
    const redrawIfIdle = (): void => {
        if (!running) {
            placeCamera();
            render();
        }
    };

    return {
        setTime(minutes) {
            const daylight = Math.min(
                1,
                Math.max(0, (minutes - sunrise) / (sunset - sunrise)),
            );
            const sky =
                daylight < 0.5
                    ? dawn.clone().lerp(noon, daylight * 2)
                    : noon.clone().lerp(dusk, (daylight - 0.5) * 2);
            const angle = daylight * Math.PI;

            scene.background = sky;
            hemisphere.color.copy(sky);
            hemisphere.intensity = 1.6 + Math.sin(angle) * 0.8;
            sun.position.set(Math.cos(angle) * 14, 4 + Math.sin(angle) * 12, 6);
            sun.intensity = 1.2 + Math.sin(angle) * 1.8;
            redrawIfIdle();
        },
        setCrowd(crowd) {
            const passesChairs =
                (crowd === 'atWall') !== (currentCrowd === 'atWall');
            currentCrowd = crowd;
            resultSheet.visible = crowd === 'atWall';

            people.forEach(({ person, route }, index) => {
                const spot = new Vector3(...crowdSpots[crowd](index));
                route.splice(
                    0,
                    route.length,
                    ...(passesChairs ? [aroundTheChairs(index)] : []),
                    spot,
                );

                // Without the animation loop nobody can walk there, so they appear in place.
                if (!running) {
                    person.position.copy(spot);
                    person.visible = crowd !== 'gone';
                    route.length = 0;
                }
            });

            redrawIfIdle();
        },
        setFocus(focus) {
            target.set(...focus);

            if (!hasFocus || reducedMotion) {
                lookAt.copy(target);
                hasFocus = true;
            }

            redrawIfIdle();
        },
        resize(width, height) {
            renderer.setSize(width, height, false);
            camera.aspect = width / Math.max(1, height);
            camera.updateProjectionMatrix();
            redrawIfIdle();
        },
        setRunning(shouldRun) {
            running = shouldRun && !reducedMotion;
            last = 0;
            renderer.setAnimationLoop(running ? tick : null);
        },
        dispose() {
            renderer.setAnimationLoop(null);
            scene.traverse((object) => {
                if (object instanceof Mesh) {
                    (object.geometry as BufferGeometry).dispose();
                }
            });
            materials.forEach((created) => created.dispose());
            renderer.dispose();
            // Hand the WebGL context back now: browsers cap how many a page may hold.
            renderer.forceContextLoss();
        },
    };
}
