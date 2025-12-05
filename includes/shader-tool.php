<?php
/**
 * Shader Tools - Liquid Glass WebGL Renderer
 * Provides reusable shader rendering functionality
 */

function renderLiquidGlassShader($backgroundImage = '/assets/purple.jpg', $imageScaleX = 1.4, $imageScaleY = 1.4) {
    ?>
     <canvas id="liquid-glass-canvas"></canvas>

    <script>
    // ------------------------------------------------------------
    // Liquid Glass WebGL Renderer
    // Adds background IMAGE_SCALE (vec2) to scale the texture
    // ------------------------------------------------------------

    // Get canvas + configure WebGL with alpha blending
    const canvas = document.getElementById('liquid-glass-canvas');
    const gl = canvas.getContext('webgl', { alpha:true });
    const dpr = window.devicePixelRatio || 1;
    canvas.width = window.innerWidth * dpr;
    canvas.height = window.innerHeight * dpr;
    gl.viewport(0,0,canvas.width,canvas.height);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

    // ------------------------------------------------------------
    // Helper functions: shader + program creation
    // ------------------------------------------------------------
    function createShader(gl, type, src){
        const s = gl.createShader(type);
        gl.shaderSource(s, src);
        gl.compileShader(s);
        if(!gl.getShaderParameter(s, gl.COMPILE_STATUS))
            console.error(gl.getShaderInfoLog(s));
        return s;
    }

    function createProgram(gl, vs, fs){
        const p = gl.createProgram();
        gl.attachShader(p, vs);
        gl.attachShader(p, fs);
        gl.linkProgram(p);
        if(!gl.getProgramParameter(p, gl.LINK_STATUS))
            console.error(gl.getProgramInfoLog(p));
        return p;
    }

    // ------------------------------------------------------------
    // Vertex Shader: Pass-through full-screen quad
    // a_pos = -1..1, remapped to UV 0..1
    // ------------------------------------------------------------
    const VERT = `
    attribute vec2 a_pos;
    varying vec2 vUV;
    void main(){
        vUV = a_pos * 0.5 + 0.5;
        gl_Position = vec4(a_pos, 0.0, 1.0);
    }
    `;

    // ------------------------------------------------------------
    // Fragment Shader: liquid glass lens
    // ------------------------------------------------------------
    const FRAG = `
    precision mediump float;

    varying vec2 vUV;

    // Main background texture
    uniform sampler2D uTex;

    // Canvas resolution
    uniform vec2 uRes;

    // Background image scaling factor
    // IMAGE_SCALE.xy → scales UV sampling on background
    uniform vec2 uImageScale;

    // Liquid glass panes (max 100)
    uniform vec4  uDivRects[100];
    uniform int   uNumDivs;

    const float SAMPLE_RANGE = 4.0;
    const float SAMPLE_OFFSET = 0.4;
    const float LENS_MULTIPLIER = 0.05;
    const float FISHBOWL_MULTIPLIER = 4.0;

    void main(){
        // --------------------------------------------------------
        // Background sample (with IMAGE_SCALE applied)
        // uvScaled < 0 or >1 will naturally clamp via texture params
        // --------------------------------------------------------
        vec2 uvScaled = (vUV - 0.5) / uImageScale + 0.5;
        vec4 col = texture2D(uTex, uvScaled);

        // Loop through all active lens panes
        for(int i = 0; i < 100; i++){
            if(i >= uNumDivs) break;

            vec4 rect = uDivRects[i];

            // Quick AABB check - sharp rectangular boundaries
            if(vUV.x < rect.x || vUV.x > rect.x + rect.z ||
               vUV.y < rect.y || vUV.y > rect.y + rect.w)
                continue;

            // Convert to local space 0–1 inside the pane
            vec2 local = (vUV - rect.xy) / rect.zw;

            // ----------------------------------------------------
            // Lens distortion (fishbowl)
            // ----------------------------------------------------
            vec2 lens = local - 0.5;
            lens *= (1.0 - pow(length(lens), 2.0) * LENS_MULTIPLIER * FISHBOWL_MULTIPLIER);
            lens += 0.5;

            // ----------------------------------------------------
            // Multisample blur inside lens
            // ----------------------------------------------------
            vec4 accum = vec4(0.0);
            float total = 0.0;

            for(float x = -SAMPLE_RANGE; x <= SAMPLE_RANGE; x++){
                for(float y = -SAMPLE_RANGE; y <= SAMPLE_RANGE; y++){
                    vec2 offset = vec2(x, y) * SAMPLE_OFFSET / uRes;

                    // Apply background scale here too
                    vec2 sampleUV =
                        (rect.xy + lens * rect.zw + offset - 0.5) / uImageScale + 0.5;

                    accum += texture2D(uTex, sampleUV);
                    total += 1.0;
                }
            }

            vec4 lensCol = accum / total;
            col = lensCol;
        }

        gl_FragColor = col;
    }
    `;

    // ------------------------------------------------------------
    // Load background image → upload to WebGL texture
    // ------------------------------------------------------------
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.src = "/assets/purple.jpg";

    img.onload = () => {

        // Create + configure texture
        const tex = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, tex);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
        gl.texImage2D(gl.TEXTURE_2D,0,gl.RGBA,gl.RGBA,gl.UNSIGNED_BYTE,img);

        // Build shader program
        const vs = createShader(gl, gl.VERTEX_SHADER, VERT);
        const fs = createShader(gl, gl.FRAGMENT_SHADER, FRAG);
        const program = createProgram(gl, vs, fs);
        gl.useProgram(program);

        // Fullscreen quad
        const quad = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, quad);
        gl.bufferData(gl.ARRAY_BUFFER,
            new Float32Array([-1,-1, 1,-1, -1,1, -1,1, 1,-1, 1,1]),
            gl.STATIC_DRAW
        );

        const aPos = gl.getAttribLocation(program,"a_pos");
        gl.enableVertexAttribArray(aPos);
        gl.vertexAttribPointer(aPos,2,gl.FLOAT,false,0,0);

        // Uniform handles
        const uRes        = gl.getUniformLocation(program,"uRes");
        const uDivRects   = gl.getUniformLocation(program,"uDivRects");
        const uNumDivs    = gl.getUniformLocation(program,"uNumDivs");
        const uDivRadii   = gl.getUniformLocation(program,"uDivRadii");
        const uImageScale = gl.getUniformLocation(program,"uImageScale");
        
        // Image scale
        gl.uniform2f(uImageScale, 1.2, 1.2); 

        // --------------------------------------------------------
        // Render loop
        // --------------------------------------------------------
        function render(){
            canvas.width = window.innerWidth * dpr;
            canvas.height = window.innerHeight * dpr;
            gl.viewport(0,0,canvas.width,canvas.height);
            gl.uniform2f(uRes, canvas.width, canvas.height);

            // Collect all .liquid-glass pane rectangles
            const panes = Array.from(document.querySelectorAll('.liquid-glass'));
            const rects = [];
            const radii = [];

            panes.forEach(p => {
                const r = p.getBoundingClientRect();

                rects.push([
                    r.left / window.innerWidth,
                    1.0 - (r.top + r.height)/window.innerHeight,
                    r.width / window.innerWidth,
                    r.height / window.innerHeight
                ]);

                const cs = window.getComputedStyle(p);
                const br = 20;
                radii.push(br / window.innerWidth);
            });

            const flatRects = new Float32Array(100*4);
            const flatRadii = new Float32Array(100);

            for(let i = 0; i < Math.min(rects.length, 100); i++){
                flatRects[i*4+0] = rects[i][0];
                flatRects[i*4+1] = rects[i][1];
                flatRects[i*4+2] = rects[i][2];
                flatRects[i*4+3] = rects[i][3];
                flatRadii[i] = radii[i];
            }

            gl.uniform4fv(uDivRects, flatRects);
            gl.uniform1fv(uDivRadii, flatRadii);
            gl.uniform1i(uNumDivs, Math.min(rects.length,100));

            // Draw fullscreen quad
            gl.drawArrays(gl.TRIANGLES,0,6);
            requestAnimationFrame(render);
        }

        render();
    };
    </script>
    <?php
}

function getLiquidGlassStyles() {
    ?>
    <style>
    .liquid-glass {
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 30px rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }

    .liquid-glass .content {
        position: relative;
        z-index: 1;
        padding: 16px;
        color: #fff;
    }

    .content-extern {
        position: relative;
        z-index: 1;
        padding: 16px;
        color: #fff;
    }

    #liquid-glass-canvas {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0; /* behind all content */
    }
    </style>
    <?php
}
?>