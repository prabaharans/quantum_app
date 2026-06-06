/* QuantumPHP Studio - Frontend Application */

// Global State
let numQubits = 3;
const timelineSteps = 12;
let gates = [];
let selectedPaletteGate = 'h';
let currentSimStep = -1; // -1 means final step
let simulationResult = null;
let blochSpheres = {};

// Three.js Bloch Sphere Class
class BlochSphere {
    constructor(container, qubitIndex) {
        this.container = container;
        this.qubitIndex = qubitIndex;
        
        // Create WebGL Renderer
        this.renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        this.renderer.setSize(container.clientWidth, container.clientHeight);
        this.renderer.setPixelRatio(window.devicePixelRatio || 1);
        container.appendChild(this.renderer.domElement);

        // Create Scene & Camera
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 100);
        this.camera.position.set(2.0, 1.6, 2.0);
        this.camera.lookAt(0, 0, 0);

        // Add OrbitControls for mouse interaction
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.08;
        this.controls.enableZoom = false; // Disable page scrolling zoom conflict
        this.controls.autoRotate = false;

        // Create Sphere Grid Outline (radius 1)
        const sphereGeo = new THREE.SphereGeometry(1, 32, 16);
        const sphereMat = new THREE.MeshBasicMaterial({
            color: 0x475569,
            wireframe: true,
            transparent: true,
            opacity: 0.12
        });
        const sphereMesh = new THREE.Mesh(sphereGeo, sphereMat);
        this.scene.add(sphereMesh);

        // Add latitude/longitude rings
        this.createRings();

        // Create Axes (Bloch X, Y, Z)
        this.createAxes();

        // Add State Vector Arrow (Initial state |0>, pointing to +z, which is Y-up in Three.js)
        const dir = new THREE.Vector3(0, 1, 0);
        const origin = new THREE.Vector3(0, 0, 0);
        this.arrow = new THREE.ArrowHelper(dir, origin, 1.0, 0x0be5c8, 0.2, 0.08);
        this.scene.add(this.arrow);

        // Add labels for axes
        this.createLabels();

        // Start render loop
        this.animate();
    }

    createRings() {
        const ringMat = new THREE.LineBasicMaterial({ color: 0x475569, transparent: true, opacity: 0.35 });
        
        // Equator ring (X-Y plane -> Three.js Z-X plane)
        const eqGeo = new THREE.BufferGeometry();
        const points = [];
        for (let i = 0; i <= 64; i++) {
            let theta = (i / 64) * Math.PI * 2;
            points.push(new THREE.Vector3(Math.cos(theta), 0, Math.sin(theta)));
        }
        eqGeo.setFromPoints(points);
        this.scene.add(new THREE.Line(eqGeo, ringMat));

        // X-Z ring (Three.js Z-Y plane)
        const xzGeo = new THREE.BufferGeometry();
        const pointsXZ = [];
        for (let i = 0; i <= 64; i++) {
            let theta = (i / 64) * Math.PI * 2;
            pointsXZ.push(new THREE.Vector3(0, Math.cos(theta), Math.sin(theta)));
        }
        xzGeo.setFromPoints(pointsXZ);
        this.scene.add(new THREE.Line(xzGeo, ringMat));
    }

    createAxes() {
        const axisMatZ = new THREE.LineBasicMaterial({ color: 0x3b82f6, transparent: true, opacity: 0.7 }); // Blue (Z)
        const axisMatY = new THREE.LineBasicMaterial({ color: 0x8b5cf6, transparent: true, opacity: 0.7 }); // Purple (Y)
        const axisMatX = new THREE.LineBasicMaterial({ color: 0x0be5c8, transparent: true, opacity: 0.7 }); // Teal (X)

        // Three.js mappings for Bloch Sphere standard view:
        // Bloch Z (up/down) -> Three.js Y
        // Bloch Y (left/right) -> Three.js X
        // Bloch X (front/back) -> Three.js Z

        // Z-axis line (vertical)
        const zGeo = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(0, -1.05, 0), new THREE.Vector3(0, 1.05, 0)]);
        this.scene.add(new THREE.Line(zGeo, axisMatZ));

        // Y-axis line (horizontal left-right)
        const yGeo = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(-1.05, 0, 0), new THREE.Vector3(1.05, 0, 0)]);
        this.scene.add(new THREE.Line(yGeo, axisMatY));

        // X-axis line (front-back)
        const xGeo = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(0, 0, -1.05), new THREE.Vector3(0, 0, 1.05)]);
        this.scene.add(new THREE.Line(xGeo, axisMatX));
    }

    createLabels() {
        // Label poles using simple axis labels
        // We will just draw small glowing dots at the poles to represent |0>, |1>
        const poleGeo = new THREE.SphereGeometry(0.03, 8, 8);
        const northMat = new THREE.MeshBasicMaterial({ color: 0x3b82f6 }); // |0> Blue
        const southMat = new THREE.MeshBasicMaterial({ color: 0xf43f5e }); // |1> Pink

        const north = new THREE.Mesh(poleGeo, northMat);
        north.position.set(0, 1.0, 0);
        this.scene.add(north);

        const south = new THREE.Mesh(poleGeo, southMat);
        south.position.set(0, -1.0, 0);
        this.scene.add(south);
    }

    update(x, y, z) {
        // Map Bloch coordinates to Three.js coordinates:
        // Three.js X = Bloch Y
        // Three.js Y = Bloch Z
        // Three.js Z = Bloch X
        const dir = new THREE.Vector3(y, z, x);
        const length = dir.length();
        
        if (length > 0.001) {
            dir.normalize();
        } else {
            // Mixed state at center (fully entangled), point arrow upwards but zero length
            dir.set(0, 1, 0);
        }

        // Limit maximum display length to 1.0
        const displayLength = Math.min(length, 1.0);

        this.arrow.setDirection(dir);
        this.arrow.setLength(displayLength, 0.18, 0.06);

        // If entangled (mixed state, Bloch length < 0.9), draw vector orange
        if (length < 0.85) {
            this.arrow.setColor(new THREE.Color(0xf97316)); // Orange
        } else {
            this.arrow.setColor(new THREE.Color(0x0be5c8)); // Teal
        }
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
    }

    resize() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }

    destroy() {
        this.renderer.dispose();
        this.container.innerHTML = '';
    }
}

// App Initialization
document.addEventListener('DOMContentLoaded', () => {
    initUI();
    loadPresets();
    loadSavedCircuits();
    triggerSimulation();
});

// Setup DOM elements and event listeners
function initUI() {
    // Qubit select change
    document.getElementById('qubit-select').addEventListener('change', (e) => {
        numQubits = parseInt(e.target.value);
        gates = []; // reset gates
        currentSimStep = -1;
        renderCircuitGrid();
        triggerSimulation();
    });

    // Gate Palette click selection
    const gateBtns = document.querySelectorAll('.gate-btn');
    gateBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            gateBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedPaletteGate = btn.getAttribute('data-gate');
        });
    });

    // Clear circuit
    document.getElementById('clear-circuit-btn').addEventListener('click', () => {
        gates = [];
        currentSimStep = -1;
        renderCircuitGrid();
        triggerSimulation();
    });

    // Manual Simulate button
    document.getElementById('simulate-circuit-btn').addEventListener('click', () => {
        triggerSimulation();
    });

    // Load preset
    document.getElementById('load-preset-btn').addEventListener('click', () => {
        const presetId = document.getElementById('preset-select').value;
        if (presetId) {
            loadPresetCircuit(presetId);
        }
    });

    // Save circuit
    document.getElementById('save-circuit-btn').addEventListener('click', () => {
        const nameInput = document.getElementById('circuit-name-input');
        const name = nameInput.value.trim();
        if (name === '') {
            alert('Please enter a name for the circuit.');
            return;
        }
        saveCircuit(name);
    });

    // Copy PHP Code
    document.getElementById('copy-code-btn').addEventListener('click', () => {
        const codeElement = document.getElementById('php-code-display');
        navigator.clipboard.writeText(codeElement.innerText).then(() => {
            const copyBtn = document.getElementById('copy-code-btn');
            copyBtn.innerText = 'Copied!';
            setTimeout(() => {
                copyBtn.innerText = 'Copy';
            }, 2000);
        });
    });

    // Setup modal actions
    document.getElementById('modal-cancel-btn').addEventListener('click', hideModal);

    // Render initial grid
    renderCircuitGrid();
}

// Generate the timelines grid layout in HTML
function renderCircuitGrid() {
    const grid = document.getElementById('circuit-grid');
    grid.innerHTML = '';

    // Create Connection lines overlay container
    const connContainer = document.createElement('div');
    connContainer.className = 'circuit-connections-container';
    connContainer.id = 'circuit-connections';
    grid.appendChild(connContainer);

    for (let q = 0; q < numQubits; q++) {
        const row = document.createElement('div');
        row.className = 'qubit-track';
        row.id = `qubit-track-${q}`;

        // Qubit Label
        const label = document.createElement('div');
        label.className = 'qubit-label';
        label.innerText = `q[${q}]`;
        row.appendChild(label);

        // Qubit line background
        const line = document.createElement('div');
        line.className = 'qubit-line';
        row.appendChild(line);

        // Timeline slots grid
        const slots = document.createElement('div');
        slots.className = 'timeline-slots';

        for (let s = 0; s < timelineSteps; s++) {
            const slot = document.createElement('div');
            slot.className = 'gate-slot';
            slot.setAttribute('data-qubit', q);
            slot.setAttribute('data-step', s);

            // Add Click listener to slot
            slot.addEventListener('click', (e) => {
                // If clicking directly on slot, or slot children but not gate nodes
                if (e.target.className === 'gate-slot') {
                    handleSlotClick(q, s);
                }
            });

            // Find if a gate exists here
            const gate = gates.find(g => g.target === q && g.step === s);
            const gateTarget2 = gates.find(g => g.target2 === q && g.step === s && g.type === 'swap');

            if (gate) {
                const gateNode = createGateNode(gate);
                slot.appendChild(gateNode);
            } else if (gateTarget2) {
                // Render SWAP endpoint node
                const swapNode = document.createElement('div');
                swapNode.className = 'placed-gate gate-node-swap';
                swapNode.innerHTML = '<span class="gate-name">×</span>';
                
                // Add Delete button
                const delBtn = document.createElement('div');
                delBtn.className = 'gate-delete';
                delBtn.innerText = '×';
                delBtn.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    deleteGate(gateTarget2);
                });
                swapNode.appendChild(delBtn);
                slot.appendChild(swapNode);
            }

            slots.appendChild(slot);
        }
        row.appendChild(slots);
        grid.appendChild(row);
    }

    // Draw lines after elements are placed
    setTimeout(drawMultiQubitConnections, 50);
}

// Helper to create the visual gate element
function createGateNode(gate) {
    const node = document.createElement('div');
    node.className = `placed-gate gate-node-${gate.type}`;
    
    let label = gate.type.toUpperCase();
    let sub = '';

    if (gate.type === 'rx' || gate.type === 'ry' || gate.type === 'rz') {
        const rawAngle = gate.angle || 0;
        let niceAngle = rawAngle.toFixed(2);
        // Clean representation of standard angles
        if (Math.abs(rawAngle - Math.PI) < 0.05) niceAngle = 'π';
        else if (Math.abs(rawAngle - Math.PI/2) < 0.05) niceAngle = 'π/2';
        else if (Math.abs(rawAngle - Math.PI/4) < 0.05) niceAngle = 'π/4';
        else if (Math.abs(rawAngle - 3*Math.PI/4) < 0.05) niceAngle = '3π/4';
        
        sub = niceAngle;
    } else if (gate.type === 'cx') {
        // CNOT target is drawn as a + sign inside circle
        label = '⊕';
    } else if (gate.type === 'cz') {
        label = '●';
    } else if (gate.type === 'swap') {
        label = '×';
    } else if (gate.type === 'measure') {
        label = '🎛️';
    } else if (gate.type === 'reset') {
        label = '↺';
    }

    node.innerHTML = `<span class="gate-name">${label}</span>`;
    if (sub !== '') {
        const subEl = document.createElement('span');
        subEl.className = 'gate-param';
        subEl.innerText = sub;
        node.appendChild(subEl);
    }

    // Add delete trigger button
    const delBtn = document.createElement('div');
    delBtn.className = 'gate-delete';
    delBtn.innerText = '×';
    delBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteGate(gate);
    });
    node.appendChild(delBtn);

    // If gate is conditional, show a small visual marker
    if (gate.conditional) {
        const marker = document.createElement('div');
        marker.className = 'conditional-marker';
        marker.style.position = 'absolute';
        marker.style.bottom = '-3px';
        marker.style.left = '50%';
        marker.style.transform = 'translateX(-50%)';
        marker.style.width = '6px';
        marker.style.height = '6px';
        marker.style.borderRadius = '50%';
        marker.style.background = 'var(--accent-pink)';
        marker.style.boxShadow = '0 0 5px var(--accent-pink)';
        marker.title = `Conditional on q[${gate.conditional.qubit}] = ${gate.conditional.value}`;
        node.appendChild(marker);
    }

    return node;
}

// Render vertical connections for Multi-qubit gates (CX, CZ, SWAP)
function drawMultiQubitConnections() {
    const container = document.getElementById('circuit-connections');
    if (!container) return;
    container.innerHTML = '';

    gates.forEach(g => {
        const step = g.step;
        
        // CNOT (CX) and CZ connections
        if ((g.type === 'cx' || g.type === 'cz') && g.controls && g.controls.length > 0) {
            const ctrlQubit = g.controls[0];
            const targetQubit = g.target;

            const targetSlot = document.querySelector(`.gate-slot[data-qubit="${targetQubit}"][data-step="${step}"]`);
            const ctrlSlot = document.querySelector(`.gate-slot[data-qubit="${ctrlQubit}"][data-step="${step}"]`);

            if (targetSlot && ctrlSlot) {
                const targetRect = targetSlot.getBoundingClientRect();
                const ctrlRect = ctrlSlot.getBoundingClientRect();
                const gridRect = document.getElementById('circuit-grid').getBoundingClientRect();

                // Compute relative coordinates
                const left = targetRect.left - gridRect.left + targetRect.width / 2 - 1;
                const top = Math.min(targetRect.top, ctrlRect.top) - gridRect.top + targetRect.height / 2;
                const height = Math.abs(targetRect.top - ctrlRect.top);

                // Draw line
                const line = document.createElement('div');
                line.className = 'cnot-connection-line';
                line.style.left = `${left}px`;
                line.style.top = `${top}px`;
                line.style.height = `${height}px`;
                container.appendChild(line);

                // Draw control dot on control track
                const dot = document.createElement('div');
                dot.className = 'cnot-control-dot';
                dot.style.left = `${left}px`;
                dot.style.top = `${ctrlRect.top - gridRect.top + ctrlRect.height / 2 - 5}px`;
                container.appendChild(dot);
            }
        }

        // SWAP connections
        if (g.type === 'swap' && g.target2 !== null) {
            const q1 = g.target;
            const q2 = g.target2;

            const slot1 = document.querySelector(`.gate-slot[data-qubit="${q1}"][data-step="${step}"]`);
            const slot2 = document.querySelector(`.gate-slot[data-qubit="${q2}"][data-step="${step}"]`);

            if (slot1 && slot2) {
                const rect1 = slot1.getBoundingClientRect();
                const rect2 = slot2.getBoundingClientRect();
                const gridRect = document.getElementById('circuit-grid').getBoundingClientRect();

                const left = rect1.left - gridRect.left + rect1.width / 2 - 1;
                const top = Math.min(rect1.top, rect2.top) - gridRect.top + rect1.height / 2;
                const height = Math.abs(rect1.top - rect2.top);

                // Draw line
                const line = document.createElement('div');
                line.className = 'swap-connection-line';
                line.style.left = `${left}px`;
                line.style.top = `${top}px`;
                line.style.height = `${height}px`;
                container.appendChild(line);
            }
        }
    });
}

// Handle clicking on an empty slot to place selected gate
function handleSlotClick(qubit, step) {
    // If a gate already exists in this step/qubit, do nothing (should delete first)
    if (gates.some(g => (g.target === qubit || g.target2 === qubit || (g.type === 'swap' && g.target2 === qubit)) && g.step === step)) {
        return;
    }

    if (selectedPaletteGate === 'rx' || selectedPaletteGate === 'ry' || selectedPaletteGate === 'rz') {
        // Open angle config modal
        showAngleModal(qubit, step, selectedPaletteGate);
    } else if (selectedPaletteGate === 'cx' || selectedPaletteGate === 'cz') {
        // Open control selector modal
        showControlModal(qubit, step, selectedPaletteGate);
    } else if (selectedPaletteGate === 'swap') {
        // Open swap target modal
        showSwapModal(qubit, step);
    } else {
        // Simple 1-qubit gate
        gates.push({
            type: selectedPaletteGate,
            target: qubit,
            controls: null,
            angle: null,
            step: step,
            target2: null
        });
        renderCircuitGrid();
        triggerSimulation();
    }
}

// Delete gate helper
function deleteGate(gate) {
    gates = gates.filter(g => g !== gate);
    renderCircuitGrid();
    triggerSimulation();
}

// Modal management
function showModal(title, bodyHtml, onSave) {
    document.getElementById('modal-title').innerText = title;
    
    const bodyContainer = document.getElementById('modal-body');
    bodyContainer.innerHTML = bodyHtml;
    
    const saveBtn = document.getElementById('modal-save-btn');
    // Recreate save button to clear previous event listeners
    const newSaveBtn = saveBtn.cloneNode(true);
    saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
    
    newSaveBtn.addEventListener('click', () => {
        if (onSave()) {
            hideModal();
        }
    });

    document.getElementById('gate-config-modal').classList.add('active');
}

function hideModal() {
    document.getElementById('gate-config-modal').classList.remove('active');
}

// Configuration Modals
function showAngleModal(qubit, step, type) {
    const html = `
        <div class="control-group">
            <label for="gate-angle-input">Rotation Angle (radians or e.g. pi/2)</label>
            <input type="text" id="gate-angle-input" value="pi/2" placeholder="e.g. pi/2, 1.5">
        </div>
        <div class="control-group mt-2">
            <label for="gate-conditional-check">
                <input type="checkbox" id="gate-conditional-check"> Make Conditional (Teleportation)
            </label>
        </div>
        <div id="conditional-options" class="control-group mt-2" style="display:none;">
            <label for="cond-qubit-select">Execute if qubit...</label>
            <select id="cond-qubit-select">
                ${Array.from({length: numQubits}, (_, i) => `<option value="${i}">q[${i}]</option>`).join('')}
            </select>
            <label for="cond-value-select" class="mt-2">is equal to...</label>
            <select id="cond-value-select">
                <option value="1" selected>1</option>
                <option value="0">0</option>
            </select>
        </div>
    `;

    showModal(`Configure ${type.toUpperCase()} Gate`, html, () => {
        const input = document.getElementById('gate-angle-input').value;
        const angle = parseAngle(input);
        
        let conditional = null;
        const condCheck = document.getElementById('gate-conditional-check').checked;
        if (condCheck) {
            conditional = {
                qubit: parseInt(document.getElementById('cond-qubit-select').value),
                value: parseInt(document.getElementById('cond-value-select').value)
            };
        }

        gates.push({
            type: type,
            target: qubit,
            controls: null,
            angle: angle,
            step: step,
            target2: null,
            conditional: conditional
        });
        
        renderCircuitGrid();
        triggerSimulation();
        return true;
    });

    // Toggle conditional view
    document.getElementById('gate-conditional-check').addEventListener('change', (e) => {
        document.getElementById('conditional-options').style.display = e.target.checked ? 'flex' : 'none';
    });
}

function showControlModal(qubit, step, type) {
    // List potential control qubits (disjoint from target qubit)
    const options = Array.from({length: numQubits}, (_, i) => i)
        .filter(q => q !== qubit)
        .map(q => `<option value="${q}">q[${q}]</option>`)
        .join('');

    const html = `
        <div class="control-group">
            <label for="gate-control-select">Control Qubit</label>
            <select id="gate-control-select">${options}</select>
        </div>
        <div class="control-group mt-2">
            <label for="gate-conditional-check">
                <input type="checkbox" id="gate-conditional-check"> Make Conditional (Teleportation)
            </label>
        </div>
        <div id="conditional-options" class="control-group mt-2" style="display:none;">
            <label for="cond-qubit-select">Execute if qubit...</label>
            <select id="cond-qubit-select">
                ${Array.from({length: numQubits}, (_, i) => `<option value="${i}">q[${i}]</option>`).join('')}
            </select>
            <label for="cond-value-select" class="mt-2">is equal to...</label>
            <select id="cond-value-select">
                <option value="1" selected>1</option>
                <option value="0">0</option>
            </select>
        </div>
    `;

    showModal(`Configure Controlled-${type === 'cx' ? 'NOT' : 'Z'}`, html, () => {
        const controlQubit = parseInt(document.getElementById('gate-control-select').value);
        
        let conditional = null;
        const condCheck = document.getElementById('gate-conditional-check').checked;
        if (condCheck) {
            conditional = {
                qubit: parseInt(document.getElementById('cond-qubit-select').value),
                value: parseInt(document.getElementById('cond-value-select').value)
            };
        }

        gates.push({
            type: type,
            target: qubit,
            controls: [controlQubit],
            angle: null,
            step: step,
            target2: null,
            conditional: conditional
        });
        
        renderCircuitGrid();
        triggerSimulation();
        return true;
    });

    // Toggle conditional view
    document.getElementById('gate-conditional-check').addEventListener('change', (e) => {
        document.getElementById('conditional-options').style.display = e.target.checked ? 'flex' : 'none';
    });
}

function showSwapModal(qubit, step) {
    const options = Array.from({length: numQubits}, (_, i) => i)
        .filter(q => q !== qubit)
        .map(q => `<option value="${q}">q[${q}]</option>`)
        .join('');

    const html = `
        <div class="control-group">
            <label for="gate-target2-select">Swap with Qubit</label>
            <select id="gate-target2-select">${options}</select>
        </div>
    `;

    showModal('Configure SWAP Gate', html, () => {
        const target2 = parseInt(document.getElementById('gate-target2-select').value);
        
        // Ensure no collision
        if (gates.some(g => (g.target === target2 || g.target2 === target2) && g.step === step)) {
            alert(`A gate already exists on q[${target2}] at this step.`);
            return false;
        }

        gates.push({
            type: 'swap',
            target: qubit,
            controls: null,
            angle: null,
            step: step,
            target2: target2
        });
        
        renderCircuitGrid();
        triggerSimulation();
        return true;
    });
}

// Evaluate inputs like "pi", "pi/2", "-pi/4"
function parseAngle(input) {
    input = input.trim().toLowerCase();
    if (input === '') return 0.0;
    
    if (input.includes('pi')) {
        let factor = 1.0;
        
        // Remove multiplied signs e.g. 3*pi
        let cleanInput = input.replace('*', '');
        let parts = cleanInput.split('/');
        
        let numerator = parts[0].replace('pi', '').trim();
        if (numerator === '' || numerator === '+') factor = 1.0;
        else if (numerator === '-') factor = -1.0;
        else factor = parseFloat(numerator);

        if (isNaN(factor)) factor = 1.0;

        if (parts[1]) {
            const denominator = parseFloat(parts[1]);
            if (!isNaN(denominator) && denominator !== 0) {
                factor = factor / denominator;
            }
        }
        return factor * Math.PI;
    }
    return parseFloat(input) || 0.0;
}

// API Interactions
function triggerSimulation() {
    const payload = {
        action: 'simulate',
        circuit: {
            numQubits: numQubits,
            gates: gates
        }
    };

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => {
        if (!res.ok) throw new Error('Simulation failed.');
        return res.json();
    })
    .then(data => {
        if (data.status === 'success') {
            simulationResult = data;
            
            // Re-render Step Selectors
            renderStepSelectors(data.stepByStep);

            // Display results for current view step (default to final step, which is -1)
            displayStepResults(currentSimStep);
            
            // Render Shots Stats Chart
            renderShotsChart(data.shots);

            // Populate PHP Code Export
            document.getElementById('php-code-display').innerText = data.phpCode;
        } else {
            console.error('API Simulation error:', data.message);
        }
    })
    .catch(err => {
        console.error('Network or simulation error:', err);
    });
}

// Populate the row of step buttons above the timeline grid
function renderStepSelectors(stepByStepData) {
    const container = document.getElementById('step-selector-container');
    container.innerHTML = '';

    // Step -1 (Initial state)
    const initBtn = document.createElement('div');
    initBtn.className = `step-bubble ${currentSimStep === -1 ? 'active' : ''}`;
    initBtn.innerText = 'Start';
    initBtn.title = 'Initial State |00...0>';
    initBtn.addEventListener('click', () => {
        currentSimStep = -1;
        updateActiveStepBubble();
        displayStepResults(-1);
    });
    container.appendChild(initBtn);

    // Dynamic steps indices (0 to max step)
    const stepIndices = Object.keys(stepByStepData).map(Number).filter(s => s >= 0).sort((a,b)=>a-b);
    
    stepIndices.forEach(idx => {
        const btn = document.createElement('div');
        btn.className = `step-bubble ${currentSimStep === idx ? 'active' : ''}`;
        btn.innerText = idx;
        btn.title = `State after executing Step ${idx}`;
        btn.addEventListener('click', () => {
            currentSimStep = idx;
            updateActiveStepBubble();
            displayStepResults(idx);
        });
        container.appendChild(btn);
    });

    // Final state selector (shorthand to look at the last index)
    const maxIdx = stepIndices.length > 0 ? stepIndices[stepIndices.length - 1] : -1;
    const finalBtn = document.createElement('div');
    finalBtn.className = `step-bubble final ${(currentSimStep === maxIdx || (currentSimStep === -1 && maxIdx === -1)) ? 'active' : ''}`;
    finalBtn.innerText = 'Final State';
    finalBtn.addEventListener('click', () => {
        // Set to maxIdx or -1 if empty
        currentSimStep = maxIdx;
        updateActiveStepBubble();
        displayStepResults(maxIdx);
    });
    container.appendChild(finalBtn);
}

function updateActiveStepBubble() {
    const bubbles = document.querySelectorAll('.step-bubble');
    bubbles.forEach(b => b.classList.remove('active'));

    // Find bubble matching currentSimStep
    bubbles.forEach(b => {
        if (b.innerText === String(currentSimStep) && !b.classList.contains('final')) {
            b.classList.add('active');
        }
    });

    // Handle "Start" bubble (Start is -1)
    if (currentSimStep === -1) {
        bubbles[0].classList.add('active');
    }
}

// Display results (Dirac state, table, Bloch spheres) for the chosen step
function displayStepResults(stepIdx) {
    if (!simulationResult || !simulationResult.stepByStep) return;

    const stepData = simulationResult.stepByStep[stepIdx];
    if (!stepData) return;

    // 1. Update Dirac displays
    const diracEl = document.getElementById('dirac-display');
    diracEl.innerText = `|ψ⟩ = ${stepData.dirac}`;

    // 2. Render Statevector Amplitudes Table
    const tableBody = document.getElementById('statevector-table-body');
    tableBody.innerHTML = '';
    
    stepData.amplitudes.forEach(amp => {
        const tr = document.createElement('tr');
        
        // Highlight states with non-zero probability
        if (amp.prob > 0.0001) {
            tr.style.background = 'rgba(11, 229, 200, 0.03)';
        }

        // State bin label
        const stateTd = document.createElement('td');
        stateTd.className = 'state-label';
        stateTd.innerText = `|${amp.state}⟩`;
        tr.appendChild(stateTd);

        // Amplitude complex format
        const ampTd = document.createElement('td');
        ampTd.className = 'amp-val';
        
        let cStr = '';
        if (Math.abs(amp.imag) < 1e-4) {
            cStr = amp.real.toFixed(4);
        } else if (Math.abs(amp.real) < 1e-4) {
            cStr = `${amp.imag.toFixed(4)}i`;
        } else {
            cStr = `${amp.real.toFixed(4)} ${amp.imag >= 0 ? '+' : '-'} ${Math.abs(amp.imag).toFixed(4)}i`;
        }
        ampTd.innerText = cStr;
        tr.appendChild(ampTd);

        // Probability percentage
        const probTd = document.createElement('td');
        probTd.innerText = `${(amp.prob * 100).toFixed(1)}%`;
        tr.appendChild(probTd);

        // Probability visual bar chart
        const visualTd = document.createElement('td');
        visualTd.innerHTML = `
            <div class="prob-bar-container">
                <div class="prob-bar" style="width: ${amp.prob * 100}%"></div>
            </div>
        `;
        tr.appendChild(visualTd);

        tableBody.appendChild(tr);
    });

    // 3. Update Bloch Spheres Visualizations
    renderBlochSpheres(stepData.blochSpheres);
}

// Synchronizes and draws the WebGL 3D Bloch spheres
function renderBlochSpheres(blochData) {
    const container = document.getElementById('bloch-spheres-container');
    
    // Destroy previous scenes if qubit count changed or they don't match
    const existingCount = Object.keys(blochSpheres).length;
    if (existingCount !== numQubits) {
        // Destroy all
        Object.values(blochSpheres).forEach(bs => bs.destroy());
        blochSpheres = {};
        container.innerHTML = '';

        // Recreate grids
        for (let q = 0; q < numQubits; q++) {
            const card = document.createElement('div');
            card.className = 'bloch-card';
            card.innerHTML = `<h3>Qubit q[${q}]</h3>`;
            
            const canvasContainer = document.createElement('div');
            canvasContainer.className = 'bloch-canvas-container';
            canvasContainer.id = `bloch-canvas-${q}`;
            card.appendChild(canvasContainer);

            // Add text metrics
            const stats = document.createElement('div');
            stats.className = 'bloch-stats';
            stats.id = `bloch-stats-${q}`;
            card.appendChild(stats);

            container.appendChild(card);

            // Initialize Three.js instance
            blochSpheres[q] = new BlochSphere(canvasContainer, q);
        }
    }

    // Update coordinates and text metrics
    for (let q = 0; q < numQubits; q++) {
        const coords = blochData[q] || { x: 0, y: 0, z: 1 };
        
        // Update 3D vector arrow
        if (blochSpheres[q]) {
            blochSpheres[q].update(coords.x, coords.y, coords.z);
        }

        // Update card label stats
        const statsEl = document.getElementById(`bloch-stats-${q}`);
        if (statsEl) {
            const len = Math.sqrt(coords.x**2 + coords.y**2 + coords.z**2);
            statsEl.innerHTML = `
                X: ${coords.x.toFixed(2)} | Y: ${coords.y.toFixed(2)} | Z: ${coords.z.toFixed(2)}<br>
                <span style="color: ${len < 0.85 ? 'var(--accent-orange)' : 'var(--text-muted)'}">
                    ${len < 0.85 ? 'Entangled (Mixed)' : 'Pure State'}
                </span>
            `;
        }
    }
}

// Render the 1024 shots measurement statistics bar chart
function renderShotsChart(shotsData) {
    const container = document.getElementById('shots-chart-container');
    container.innerHTML = '';

    const keys = Object.keys(shotsData);
    if (keys.length === 0) {
        container.innerHTML = '<div class="empty-list-msg">Simulate circuit to collect measurements.</div>';
        return;
    }

    // Find maximum count for scaling
    const maxVal = Math.max(...Object.values(shotsData));

    keys.forEach(k => {
        const count = shotsData[k];
        const percent = (count / 1024 * 100).toFixed(1);
        const barWidth = maxVal > 0 ? (count / maxVal * 100) : 0;

        const row = document.createElement('div');
        row.className = 'chart-row';

        const label = document.createElement('div');
        label.className = 'chart-label';
        label.innerText = `|${k}⟩`;
        row.appendChild(label);

        const outer = document.createElement('div');
        outer.className = 'chart-bar-outer';
        outer.innerHTML = `
            <div class="chart-bar-inner" style="width: ${barWidth}%"></div>
            <div class="chart-value">${count} (${percent}%)</div>
        `;
        row.appendChild(outer);

        container.appendChild(row);
    });
}

// Presets Loading
function loadPresets() {
    fetch('api.php?action=presets')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const select = document.getElementById('preset-select');
                data.presets.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.innerText = p.name;
                    select.appendChild(opt);
                });
                // Cache presets globally for easy loading
                window.presetsData = data.presets;
            }
        });
}

function loadPresetCircuit(presetId) {
    if (!window.presetsData) return;
    const preset = window.presetsData.find(p => p.id === presetId);
    if (!preset) return;

    // Load configs
    numQubits = preset.numQubits;
    document.getElementById('qubit-select').value = numQubits;
    
    gates = JSON.parse(JSON.stringify(preset.gates)); // deep copy
    currentSimStep = -1;

    renderCircuitGrid();
    triggerSimulation();
}

// Saved Circuits Management
function loadSavedCircuits() {
    fetch('api.php?action=list_saved')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const list = document.getElementById('saved-circuits-list');
                list.innerHTML = '';

                if (data.circuits.length === 0) {
                    list.innerHTML = '<li class="empty-list-msg">No saved circuits found.</li>';
                    return;
                }

                data.circuits.forEach(c => {
                    const li = document.createElement('li');
                    
                    const nameSpan = document.createElement('span');
                    nameSpan.innerText = `${c.name} (${c.numQubits}Q)`;
                    li.appendChild(nameSpan);

                    const actions = document.createElement('div');
                    
                    const deleteSpan = document.createElement('span');
                    deleteSpan.className = 'delete-saved';
                    deleteSpan.innerHTML = '🗑️';
                    deleteSpan.title = 'Delete saved circuit';
                    deleteSpan.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (confirm(`Delete circuit "${c.name}"?`)) {
                            deleteSavedCircuit(c.name);
                        }
                    });

                    actions.appendChild(deleteSpan);
                    li.appendChild(actions);

                    li.addEventListener('click', () => {
                        loadSavedCircuit(c.name);
                    });

                    list.appendChild(li);
                });
            }
        });
}

function loadSavedCircuit(name) {
    fetch(`api.php?action=load_saved&name=${encodeURIComponent(name)}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const c = data.circuit;
                numQubits = parseInt(c.numQubits);
                document.getElementById('qubit-select').value = numQubits;
                gates = c.gates || [];
                currentSimStep = -1;
                
                document.getElementById('circuit-name-input').value = name;

                renderCircuitGrid();
                triggerSimulation();
            } else {
                alert('Failed to load circuit: ' + data.message);
            }
        });
}

function saveCircuit(name) {
    const payload = {
        action: 'save_circuit',
        name: name,
        circuit: {
            numQubits: numQubits,
            gates: gates
        }
    };

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            loadSavedCircuits();
        } else {
            alert('Save failed: ' + data.message);
        }
    });
}

function deleteSavedCircuit(name) {
    const payload = {
        action: 'delete_circuit',
        name: name
    };

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            loadSavedCircuits();
            const nameInput = document.getElementById('circuit-name-input');
            if (nameInput.value.trim() === name) {
                nameInput.value = '';
            }
        } else {
            alert('Delete failed: ' + data.message);
        }
    });
}
