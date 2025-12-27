<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap');

        /* Custom body scrollbar */
        ::-webkit-scrollbar {
            width: 16px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(10, 10, 15, 0.8);
            border-left: 1px solid rgba(0, 255, 255, 0.2);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom,
                    #0ff 0%,
                    #00b3b3 30%,
                    #008080 70%,
                    #0ff 100%);
            border-radius: 8px;
            border: 2px solid rgba(0, 50, 50, 0.6);
            box-shadow:
                0 0 10px rgba(0, 255, 255, 0.7),
                inset 0 0 10px rgba(0, 200, 200, 0.5);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom,
                    #0ff 0%,
                    #00cccc 30%,
                    #009999 70%,
                    #0ff 100%);
            box-shadow:
                0 0 15px rgba(0, 255, 255, 0.9),
                inset 0 0 15px rgba(0, 230, 230, 0.7);
        }

        ::-webkit-scrollbar-corner {
            background: rgba(10, 10, 15, 0.9);
        }

        /* Firefox scrollbar */
        html {
            scrollbar-width: thin;
            scrollbar-color: #0ff rgba(10, 10, 15, 0.8);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Orbitron', monospace;
            background: radial-gradient(circle at 30% 30%, #0a0a0f, #000000 80%);
            color: #0ff;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
            transform-style: preserve-3d;
            perspective: 1500px;
        }

        /* scanlines background */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: repeating-linear-gradient(0deg,
                    rgba(255, 255, 255, 0.03),
                    rgba(255, 255, 255, 0.03) 1px,
                    transparent 1px,
                    transparent 3px);
            pointer-events: none;
            animation: scanline 10s linear infinite;
            transform: translateZ(-500px);
        }

        @keyframes scanline {
            0% {
                background-position: 0 0;
            }

            100% {
                background-position: 20% 300%;
            }
        }

        /* particles overlay */
        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: #0ff;
            border-radius: 50%;
            opacity: 0.3;
            animation: floatParticle linear infinite;
            transform-style: preserve-3d;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(0) translateX(0) translateZ(0);
                opacity: 0.2;
            }

            50% {
                opacity: 0.6;
            }

            100% {
                transform: translateY(-100vh) translateX(50vw) translateZ(100px);
                opacity: 0;
            }
        }

        .container {
            display: grid;
            grid-template-rows: auto auto;
            gap: 2rem;
            perspective: 1500px;
            position: relative;
            z-index: 2;
            width: 85%;
            max-width: 1800px;
            margin: 2rem auto;
            padding: 1rem;
            transform-style: preserve-3d;
        }

        .error-container {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 15px;
            padding: 3rem 2rem;
            box-shadow:
                0 0 40px rgba(0, 255, 255, 0.3),
                0 0 80px rgba(0, 255, 255, 0.2) inset,
                0 20px 50px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            transform-style: preserve-3d;
            position: relative;
            overflow: hidden;
            transition: transform 0.1s ease-out, box-shadow 0.3s ease;
        }

        /* 3D edge effect */
        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg,
                    rgba(0, 255, 255, 0.7),
                    rgba(0, 255, 255, 0.3));
            border-radius: 15px 15px 0 0;
            transform: rotateX(90deg) translateY(-2.5px);
            transform-origin: top center;
        }

        /* glitch overlay */
        .error-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(0deg,
                    rgba(255, 0, 200, 0.05),
                    rgba(255, 0, 200, 0.05) 2px,
                    transparent 2px,
                    transparent 4px);
            pointer-events: none;
            mix-blend-mode: screen;
            animation: glitch 2s infinite linear;
            transform: translateZ(1px);
        }

        @keyframes glitch {
            0% {
                clip-path: inset(0 0 90% 0);
                transform: translate(-2px, -1px) translateZ(1px);
            }

            20% {
                clip-path: inset(10% 0 80% 0);
                transform: translate(2px, 1px) translateZ(1px);
            }

            40% {
                clip-path: inset(20% 0 70% 0);
                transform: translate(-1px, 2px) translateZ(1px);
            }

            60% {
                clip-path: inset(30% 0 60% 0);
                transform: translate(1px, -1px) translateZ(1px);
            }

            80% {
                clip-path: inset(40% 0 50% 0);
                transform: translate(-2px, 1px) translateZ(1px);
            }

            100% {
                clip-path: inset(0 0 90% 0);
                transform: translate(0, 0) translateZ(1px);
            }
        }

        .error_code {
            font-size: clamp(6rem, 15vw, 10rem);
            font-weight: 900;
            text-align: center;
            color: #0ff;
            text-shadow:
                0 0 10px #0ff,
                0 0 20px #0ff50,
                0 0 40px #0ff30;
            margin: 0;
            animation: flicker 4s infinite, neonShift 6s infinite alternate;
            transform: translateZ(20px);
        }

        .error_message {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            text-decoration: underline;
            color: #F2003D;
            text-shadow: 0 0 10px #F2003D50, 0 0 20px #F2003D30;
            margin-top: 0.5rem;
            text-align: center;
            animation: flicker 6s infinite, neonShift 6s infinite alternate;
            transform: translateZ(20px);
        }

        @keyframes neonShift {
            0% {
                text-shadow: 0 0 10px #0ff, 0 0 20px #0ff50, 0 0 40px #0ff30;
            }

            50% {
                text-shadow: 0 0 20px #ff77cc, 0 0 40px #ff77cc80, 0 0 60px #0ff20;
            }

            100% {
                text-shadow: 0 0 10px #0ff, 0 0 20px #0ff50, 0 0 40px #0ff30;
            }
        }

        .error_sub_message {
            font-size: clamp(1.2rem, 3vw, 1.6rem);
            color: #aaa;
            opacity: 0.8;
            font-style: italic;
            text-align: center;
            margin-top: 1rem;
            transform: translateZ(15px);
        }

        .trace-container {
            max-height: 400px;
            overflow: hidden;
            position: relative;
            transform-style: preserve-3d;
        }

        .trace {
            overflow: auto;
            font-family: 'Fira Code', monospace;
            background: rgba(0, 0, 0, 0.3);
            border-left: 4px solid #0ff;
            padding: 1rem;
            border-radius: 12px;
            color: #0ff;
            box-shadow: inset 0 0 20px rgba(0, 255, 255, 0.2);
            scrollbar-width: none;
            max-height: 70vh;
            font-size: 0.85rem;
            line-height: 1.3;
            white-space: pre-wrap;
            word-wrap: break-word;
            transform-style: preserve-3d;
            transition: transform 0.1s ease-out;
        }

        /* Custom scrollbar for WebKit browsers */
        .trace::-webkit-scrollbar {
            width: 12px;
        }

        .trace::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 0 8px 8px 0;
        }

        .trace::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, #0ff, #0a0a2a);
            border-radius: 8px;
            border: 2px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }

        .trace::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, #0ff, #00b3b3);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.8);
        }

        .trace::-webkit-scrollbar-corner {
            background: rgba(0, 0, 0, 0.2);
        }

        /* Custom scrollbar for Firefox */
        .trace {
            scrollbar-color: #0ff rgba(0, 0, 0, 0.2);
            scrollbar-width: thin;
        }

        .trace-controls {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding: 0 5px;
            transform: translateZ(10px);
        }

        .trace-btn {
            background: rgba(0, 255, 255, 0.2);
            border: 1px solid #0ff;
            color: #0ff;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Orbitron', monospace;
            transition: all 0.3s ease;
            transform: translateZ(5px);
        }

        .trace-btn:hover {
            background: rgba(0, 255, 255, 0.4);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            transform: translateZ(5px) translateY(-2px);
        }

        .trace-info {
            color: #aaa;
            font-size: 0.9rem;
            align-self: center;
        }

        @keyframes floatPanel {
            0% {
                transform:
                    rotateX(5deg) rotateY(-3deg) translateY(0px) translateZ(0);
            }

            50% {
                transform:
                    rotateX(6deg) rotateY(-2deg) translateY(-5px) translateZ(10px);
            }

            100% {
                transform:
                    rotateX(5deg) rotateY(-3deg) translateY(0px) translateZ(0);
            }
        }

        @keyframes flicker {

            0%,
            19%,
            21%,
            23%,
            25%,
            54%,
            56%,
            100% {
                opacity: 1;
            }

            20%,
            22%,
            24%,
            55% {
                opacity: 0.4;
            }
        }

        /* Collapse long trace entries */
        .trace-line {
            margin: 5px 0;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .trace-line:hover {
            background-color: rgba(0, 255, 255, 0.1);
        }

        .trace-collapsed {
            display: none;
        }

        .trace-toggle {
            color: #ff77cc;
            cursor: pointer;
            margin-right: 5px;
            user-select: none;
        }

        /* Scroll progress indicator */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(to right, #0ff, #f0f);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.7);
            z-index: 9999;
            transition: width 0.2s ease;
            transform: translateZ(100px);
        }

        /* 3D depth indicator */
        .depth-indicator {
            position: absolute;
            bottom: 10px;
            right: 10px;
            color: rgba(0, 255, 255, 0.5);
            font-size: 0.8rem;
            z-index: 100;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                width: 95%;
                margin: 1rem auto;
            }

            .error-container {
                padding: 2rem 1rem;
            }

            .trace {
                max-height: 300px;
                font-size: 0.9rem;
            }

            /* Smaller scrollbar on mobile */
            ::-webkit-scrollbar {
                width: 10px;
            }

            /* Disable 3D effects on mobile for performance */
            body {
                perspective: none;
            }

            .error-container,
            .trace {
                transform: none !important;
            }
        }
    </style>

    <?php
    $post = strpos(':', $error_message ?? '');
    $title = strtok($error_message ?? '', ':');
    $host = $_SERVER['HTTP_HOST'];

    ?>
    <title><?= "{$host} - {$title}" ?></title>
</head>

<body>
    <div class="scroll-progress" id="scrollProgress"></div>
    <div class="depth-indicator" id="depthIndicator">Depth: 0px</div>

    <div class="container">
        <div class="error-container text-center" id="errorPanel">
            <h1 class="error_code">404</h1>
            <h2 class="error_message"><?= $error_message ?></h2>
            <p class="error_sub_message"><?= $error_sub_message ?></p>
        </div>

        <?php if (!empty($trace)): ?>
            <div class="error-container text-left" id="tracePanel">
                <p class="card-subtitle">Trace:</p>
                <div class="trace-container">
                    <pre class="trace" id="traceOutput"><?= $trace ?></pre>
                </div>
                <div class="trace-controls">
                    <span class="trace-info" id="traceInfo"></span>
                    <div>
                        <button class="trace-btn" id="expandAll">Expand All</button>
                        <button class="trace-btn" id="collapseAll">Collapse All</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // create floating particles
        for (let i = 0; i < 80; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random() * 100 + 'vw';
            p.style.top = Math.random() * 100 + 'vh';
            p.style.animationDuration = (5 + Math.random() * 10) + 's';
            p.style.width = p.style.height = (1 + Math.random() * 3) + 'px';
            p.style.transform = `translateZ(${Math.random() * 200 - 100}px)`;
            document.body.appendChild(p);
        }

        // Get panels for 3D transformation
        const errorPanel = document.getElementById('errorPanel');
        const tracePanel = document.getElementById('tracePanel');
        const depthIndicator = document.getElementById('depthIndicator');

        // Set initial 3D styles
        errorPanel.style.transform = 'rotateX(5deg) rotateY(-3deg) translateZ(0)';
        if (tracePanel) {
            tracePanel.style.transform = 'rotateX(2deg) rotateY(1deg) translateZ(0)';
        }

        // Mouse move perspective effect
        document.addEventListener('mousemove', e => {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;

            // Calculate rotation and depth based on mouse position
            const rotateY = (x - 0.5) * 10; // -5 to 5 degrees
            const rotateX = (0.5 - y) * 10; // -5 to 5 degrees
            const depthZ = (x - 0.5) * 40; // -20 to 20 pixels

            // Apply transformation to error panel
            errorPanel.style.transform = `
                rotateX(${5 + rotateX}deg) 
                rotateY(${-3 + rotateY}deg) 
                translateZ(${depthZ}px)
            `;

            // Apply transformation to trace panel with opposite effect
            if (tracePanel) {
                tracePanel.style.transform = `
                    rotateX(${2 - rotateX * 0.5}deg) 
                    rotateY(${1 - rotateY * 0.5}deg) 
                    translateZ(${-depthZ * 0.5}px)
                `;
            }

            // Update depth indicator
            depthIndicator.textContent = `Depth: ${depthZ.toFixed(1)}px`;

            // Dynamic neon reflections
            document.querySelectorAll('.error_code, .error_message').forEach(el => {
                el.style.textShadow = `
                    ${x*20}px ${y*20}px 20px #0ff, 
                    ${x*30}px ${y*30}px 40px #ff77cc50, 
                    ${x*40}px ${y*40}px 60px #0ff30
                `;
            });

            // Adjust shadow based on perspective
            errorPanel.style.boxShadow = `
                0 0 40px rgba(0, 255, 255, 0.3), 
                0 0 80px rgba(0, 255, 255, 0.2) inset,
                0 ${20 + rotateX * 2}px ${50 + Math.abs(rotateY) * 5}px rgba(0, 0, 0, 0.6)
            `;
        });

        // Reset panels when mouse leaves
        document.addEventListener('mouseleave', () => {
            errorPanel.style.transform = 'rotateX(5deg) rotateY(-3deg) translateZ(0)';
            if (tracePanel) {
                tracePanel.style.transform = 'rotateX(2deg) rotateY(1deg) translateZ(0)';
            }
            depthIndicator.textContent = 'Depth: 0px';
        });

        // Scroll progress indicator
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById('scrollProgress').style.width = scrolled + '%';
        });

        // Handle long trace stacks
        document.addEventListener('DOMContentLoaded', function() {
            const traceElement = document.getElementById('traceOutput');
            if (!traceElement) return;

            const traceInfo = document.getElementById('traceInfo');
            const expandAllBtn = document.getElementById('expandAll');
            const collapseAllBtn = document.getElementById('collapseAll');

            // Format and make trace collapsible
            const traceContent = traceElement.textContent;
            const lines = traceContent.split('\n');
            let formattedTrace = '';
            let indentLevel = 0;
            let lineCount = 0;

            lines.forEach(line => {
                if (line.trim() === '') return;

                const currentIndent = line.search(/\S|$/);
                const isEntryPoint = line.includes(') at ') || line.includes('): ');

                if (isEntryPoint && indentLevel > 0) {
                    formattedTrace += `<div class="trace-collapsed" data-level="${indentLevel}">`;
                }

                if (isEntryPoint) {
                    indentLevel = currentIndent;
                    formattedTrace += `<div class="trace-line"><span class="trace-toggle" data-expanded="true">−</span> ${escapeHtml(line)}</div>`;
                } else {
                    formattedTrace += `<div class="trace-line" style="padding-left: ${currentIndent + 20}px">${escapeHtml(line)}</div>`;
                }

                lineCount++;
            });

            traceElement.innerHTML = formattedTrace;
            traceInfo.textContent = `${lineCount} lines`;

            // Add toggle functionality
            traceElement.querySelectorAll('.trace-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const isExpanded = this.getAttribute('data-expanded') === 'true';
                    this.textContent = isExpanded ? '+' : '−';
                    this.setAttribute('data-expanded', !isExpanded);

                    let nextElement = this.parentElement.nextElementSibling;
                    while (nextElement && nextElement.classList.contains('trace-line') &&
                        parseInt(nextElement.style.paddingLeft || '0') > parseInt(this.parentElement.style.paddingLeft || '0')) {
                        nextElement.style.display = isExpanded ? 'none' : 'block';
                        nextElement = nextElement.nextElementSibling;
                    }
                });
            });

            // Expand all functionality
            expandAllBtn.addEventListener('click', function() {
                traceElement.querySelectorAll('.trace-toggle').forEach(toggle => {
                    toggle.textContent = '−';
                    toggle.setAttribute('data-expanded', 'true');
                });
                traceElement.querySelectorAll('.trace-line').forEach(line => {
                    line.style.display = 'block';
                });
            });

            // Collapse all functionality
            collapseAllBtn.addEventListener('click', function() {
                traceElement.querySelectorAll('.trace-toggle').forEach(toggle => {
                    toggle.textContent = '+';
                    toggle.setAttribute('data-expanded', 'false');
                });

                // Show only the main trace entries
                traceElement.querySelectorAll('.trace-line').forEach((line, index) => {
                    if (index === 0 || line.querySelector('.trace-toggle')) {
                        line.style.display = 'block';
                    } else {
                        line.style.display = 'none';
                    }
                });
            });

            // Auto-collapse long traces initially
            if (lineCount > 50) {
                collapseAllBtn.click();
            }

            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        });
    </script>
</body>

</html>