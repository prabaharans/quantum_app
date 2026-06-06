<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuantumPHP Studio - Advanced Quantum Simulator</title>
    
    <!-- Google Fonts for premium typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Custom Premium Styles -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="glass-bg"></div>
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>

    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="brand">
                <span class="logo-icon">⚛</span>
                <div class="brand-text">
                    <h1>QuantumPHP Studio</h1>
                    <p>Advanced Quantum Simulator & Visualizer (PHP 8.5 Backend)</p>
                </div>
            </div>
            <div class="header-controls">
                <span class="badge php-badge">PHP 8.5</span>
                <span class="badge webgl-badge">WebGL 3D</span>
            </div>
        </header>

        <!-- Main Dashboard -->
        <main class="app-dashboard">
            
            <!-- Left Sidebar: Controls, Gates, and Storage -->
            <section class="sidebar-panel glass-panel">
                <div class="panel-section">
                    <h2><span class="icon">⚙</span> Configuration</h2>
                    <div class="control-group">
                        <label for="qubit-select">Qubits</label>
                        <select id="qubit-select">
                            <option value="2">2 Qubits</option>
                            <option value="3" selected>3 Qubits</option>
                            <option value="4">4 Qubits</option>
                            <option value="5">5 Qubits</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="preset-select">Presets</label>
                        <div class="row">
                            <select id="preset-select">
                                <option value="" disabled selected>Select a preset...</option>
                            </select>
                            <button id="load-preset-btn" class="btn-primary">Load</button>
                        </div>
                    </div>
                </div>

                <div class="panel-section">
                    <h2><span class="icon">🧩</span> Gate Palette</h2>
                    <p class="section-desc">Select a gate, then click on the circuit grid to place it.</p>
                    <div class="gate-palette">
                        <div class="gate-btn gate-h active" data-gate="h" title="Hadamard Gate (Superposition)">H</div>
                        <div class="gate-btn gate-x" data-gate="x" title="Pauli-X Gate (NOT)">X</div>
                        <div class="gate-btn gate-y" data-gate="y" title="Pauli-Y Gate">Y</div>
                        <div class="gate-btn gate-z" data-gate="z" title="Pauli-Z Gate (Phase Flip)">Z</div>
                        <div class="gate-btn gate-s" data-gate="s" title="S Gate (Phase pi/2)">S</div>
                        <div class="gate-btn gate-t" data-gate="t" title="T Gate (Phase pi/4)">T</div>
                        <div class="gate-btn gate-rx" data-gate="rx" title="Rx Gate (Rotation X)">Rx</div>
                        <div class="gate-btn gate-ry" data-gate="ry" title="Ry Gate (Rotation Y)">Ry</div>
                        <div class="gate-btn gate-rz" data-gate="rz" title="Rz Gate (Rotation Z)">Rz</div>
                        <div class="gate-btn gate-cx" data-gate="cx" title="CNOT Gate (Controlled-NOT)">CX</div>
                        <div class="gate-btn gate-cz" data-gate="cz" title="CZ Gate (Controlled-Z)">CZ</div>
                        <div class="gate-btn gate-swap" data-gate="swap" title="SWAP Gate">SWAP</div>
                        <div class="gate-btn gate-measure" data-gate="measure" title="Measure Gate (Classical Collapse)">M</div>
                        <div class="gate-btn gate-reset" data-gate="reset" title="Reset Gate (Measure + Force 0)">RESET</div>
                    </div>
                </div>

                <div class="panel-section">
                    <h2><span class="icon">💾</span> Saved Circuits</h2>
                    <div class="control-group">
                        <input type="text" id="circuit-name-input" placeholder="Circuit Name...">
                        <button id="save-circuit-btn" class="btn-secondary mt-2 w-100">Save Current</button>
                    </div>
                    <div class="saved-circuits-list-container">
                        <ul id="saved-circuits-list" class="styled-list">
                            <li class="empty-list-msg">No saved circuits found.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Center Panel: Circuit Builder Board -->
            <section class="center-panel">
                <div class="circuit-editor-panel glass-panel">
                    <div class="panel-header">
                        <h2><span class="icon">⚡</span> Circuit Editor</h2>
                        <div class="circuit-actions">
                            <button id="clear-circuit-btn" class="btn-danger-outline">Clear Circuit</button>
                            <button id="simulate-circuit-btn" class="btn-success">Simulate</button>
                        </div>
                    </div>

                    <!-- Step Tracker bar -->
                    <div class="timeline-step-tracker">
                        <div class="step-label">Select Simulation Step:</div>
                        <div id="step-selector-container" class="step-buttons">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Interactive Grid -->
                    <div class="circuit-grid-container">
                        <div id="circuit-grid" class="circuit-grid">
                            <!-- Populated dynamically by JS -->
                        </div>
                    </div>
                </div>

                <!-- Live Dirac State Representation -->
                <div class="state-math-panel glass-panel">
                    <div class="panel-header">
                        <h2><span class="icon">📐</span> State Vector (Ket Notation)</h2>
                    </div>
                    <div class="dirac-display-container">
                        <div id="dirac-display" class="dirac-display">|ψ⟩ = |000⟩</div>
                    </div>
                </div>

                <!-- Bloch Spheres Panel -->
                <div class="bloch-spheres-panel glass-panel">
                    <div class="panel-header">
                        <h2><span class="icon">🌐</span> 3D Bloch Spheres (Qubit State Vectors)</h2>
                        <span class="tooltip-info" title="Shows the reduced density matrix state of each qubit. Works for entangled states (mixed states collapse to the center).">ℹ️ Info</span>
                    </div>
                    <div id="bloch-spheres-container" class="bloch-spheres-grid">
                        <!-- Bloch sphere canvases will be created here -->
                    </div>
                </div>
            </section>

            <!-- Right Panel: Probability & Statistics -->
            <section class="right-panel">
                <!-- Statevector Table & Probability chart -->
                <div class="probabilities-panel glass-panel">
                    <h2><span class="icon">📊</span> State Vector Probabilities</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>State</th>
                                    <th>Amplitude</th>
                                    <th>Prob.</th>
                                    <th>Visual</th>
                                </tr>
                            </thead>
                            <tbody id="statevector-table-body">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Measurement Shots Histogram -->
                <div class="shots-panel glass-panel">
                    <h2><span class="icon">📈</span> Measurement Statistics (1024 Shots)</h2>
                    <p class="section-desc">Simulated collapse distribution of measurement gates.</p>
                    <div id="shots-chart-container" class="shots-chart-grid">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Equivalent PHP Code Exporter -->
                <div class="code-export-panel glass-panel">
                    <div class="panel-header">
                        <h2><span class="icon">💻</span> Export PHP Code</h2>
                        <button id="copy-code-btn" class="btn-text">Copy</button>
                    </div>
                    <div class="code-container">
                        <pre><code id="php-code-display" class="language-php"># Run simulation to generate PHP code...</code></pre>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- Modal for configuration options (e.g. angle setting, control setting) -->
    <div id="gate-config-modal" class="modal-overlay">
        <div class="modal-content glass-panel">
            <h3 id="modal-title">Configure Gate</h3>
            <div id="modal-body">
                <!-- Dynamic input fields -->
            </div>
            <div class="modal-actions">
                <button id="modal-cancel-btn" class="btn-secondary">Cancel</button>
                <button id="modal-save-btn" class="btn-primary">Apply</button>
            </div>
        </div>
    </div>

    <!-- Three.js and OrbitControls for 3D Bloch Spheres -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
    
    <!-- Client Application Code -->
    <script src="js/app.js"></script>
</body>
</html>
