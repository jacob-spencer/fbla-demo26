
class ShaderBackground {
  constructor(canvasId, imageUrl) {
    this.canvas = document.getElementById(canvasId);
    this.gl = this.canvas.getContext("webgl");
    this.imageUrl = imageUrl;
    this.mouse = [0, 0];
    this.startTime = performance.now();
    
    this.init();
  }

  init() {
    this.setCanvasSize();
    this.setupShaders();
    this.setupBuffer();
    this.setupTexture();
    this.setupEventListeners();
    this.render();
  }

  setCanvasSize() {
    this.canvas.width = window.innerWidth;
    this.canvas.height = window.innerHeight;
  }

  setupShaders() {
    const vsSource = `
      attribute vec2 position;
      void main() {
        gl_Position = vec4(position, 0.0, 1.0);
      }
    `;

    const fsSource = `
      precision mediump float;
      uniform vec3 iResolution;
      uniform float iTime;
      uniform vec4 iMouse;
      uniform sampler2D iChannel0;

      void mainImage(out vec4 fragColor, in vec2 fragCoord) {
        const float NUM_ZERO = 0.0;
        const float NUM_ONE = 1.0;
        const float NUM_HALF = 0.5;
        const float NUM_TWO = 2.0;
        const float POWER_EXPONENT = 6.0;
        const float MASK_MULTIPLIER_1 = 10000.0;
        const float MASK_MULTIPLIER_2 = 9500.0;
        const float MASK_MULTIPLIER_3 = 11000.0;
        const float LENS_MULTIPLIER = 5000.0;
        const float MASK_STRENGTH_1 = 8.0;
        const float MASK_STRENGTH_2 = 16.0;
        const float MASK_STRENGTH_3 = 2.0;
        const float MASK_THRESHOLD_1 = 0.95;
        const float MASK_THRESHOLD_2 = 0.9;
        const float MASK_THRESHOLD_3 = 1.5;
        const float SAMPLE_RANGE = 4.0;
        const float SAMPLE_OFFSET = 0.5;
        const float GRADIENT_RANGE = 0.2;
        const float GRADIENT_OFFSET = 0.1;
        const float GRADIENT_EXTREME = -1000.0;
        const float LIGHTING_INTENSITY = 0.3;

        vec2 uv = fragCoord / iResolution.xy;
        vec2 mouse = iMouse.xy;
        if (length(mouse) < NUM_ONE) {
          mouse = iResolution.xy / NUM_TWO;
        }
        vec2 m2 = (uv - mouse / iResolution.xy);

        float roundedBox = pow(abs(m2.x * iResolution.x / iResolution.y), POWER_EXPONENT) + pow(abs(m2.y), POWER_EXPONENT);
        float rb1 = clamp((NUM_ONE - roundedBox * MASK_MULTIPLIER_1) * MASK_STRENGTH_1, NUM_ZERO, NUM_ONE);
        float rb2 = clamp((MASK_THRESHOLD_1 - roundedBox * MASK_MULTIPLIER_2) * MASK_STRENGTH_2, NUM_ZERO, NUM_ONE) -
          clamp(pow(MASK_THRESHOLD_2 - roundedBox * MASK_MULTIPLIER_2, NUM_ONE) * MASK_STRENGTH_2, NUM_ZERO, NUM_ONE);
        float rb3 = clamp((MASK_THRESHOLD_3 - roundedBox * MASK_MULTIPLIER_3) * MASK_STRENGTH_3, NUM_ZERO, NUM_ONE) -
          clamp(pow(NUM_ONE - roundedBox * MASK_MULTIPLIER_3, NUM_ONE) * MASK_STRENGTH_3, NUM_ZERO, NUM_ONE);

        fragColor = vec4(NUM_ZERO);
        float transition = smoothstep(NUM_ZERO, NUM_ONE, rb1 + rb2);

        if (transition > NUM_ZERO) {
          vec2 lens = ((uv - NUM_HALF) * NUM_ONE * (NUM_ONE - roundedBox * LENS_MULTIPLIER) + NUM_HALF);
          float total = NUM_ZERO;
          for (float x = -SAMPLE_RANGE; x <= SAMPLE_RANGE; x++) {
            for (float y = -SAMPLE_RANGE; y <= SAMPLE_RANGE; y++) {
              vec2 offset = vec2(x, y) * SAMPLE_OFFSET / iResolution.xy;
              fragColor += texture2D(iChannel0, offset + lens);
              total += NUM_ONE;
            }
          }
          fragColor /= total;

          float gradient = clamp((clamp(m2.y, NUM_ZERO, GRADIENT_RANGE) + GRADIENT_OFFSET) / NUM_TWO, NUM_ZERO, NUM_ONE) +
            clamp((clamp(-m2.y, GRADIENT_EXTREME, GRADIENT_RANGE) * rb3 + GRADIENT_OFFSET) / NUM_TWO, NUM_ZERO, NUM_ONE);
          vec4 lighting = clamp(fragColor + vec4(rb1) * gradient + vec4(rb2) * LIGHTING_INTENSITY, NUM_ZERO, NUM_ONE);

          fragColor = mix(texture2D(iChannel0, uv), lighting, transition);
        } else {
          fragColor = texture2D(iChannel0, uv);
        }
      }

      void main() {
        mainImage(gl_FragColor, gl_FragCoord.xy);
      }
    `;

    const vs = this.createShader(this.gl.VERTEX_SHADER, vsSource);
    const fs = this.createShader(this.gl.FRAGMENT_SHADER, fsSource);
    
    this.program = this.gl.createProgram();
    this.gl.attachShader(this.program, vs);
    this.gl.attachShader(this.program, fs);
    this.gl.linkProgram(this.program);
    this.gl.useProgram(this.program);

    this.uniforms = {
      resolution: this.gl.getUniformLocation(this.program, "iResolution"),
      time: this.gl.getUniformLocation(this.program, "iTime"),
      mouse: this.gl.getUniformLocation(this.program, "iMouse"),
      texture: this.gl.getUniformLocation(this.program, "iChannel0")
    };
  }

  createShader(type, source) {
    const shader = this.gl.createShader(type);
    this.gl.shaderSource(shader, source);
    this.gl.compileShader(shader);

    if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
      console.error("Shader error:", this.gl.getShaderInfoLog(shader));
      this.gl.deleteShader(shader);
      return null;
    }
    return shader;
  }

  setupBuffer() {
    const buffer = this.gl.createBuffer();
    this.gl.bindBuffer(this.gl.ARRAY_BUFFER, buffer);
    this.gl.bufferData(
      this.gl.ARRAY_BUFFER,
      new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]),
      this.gl.STATIC_DRAW
    );

    const position = this.gl.getAttribLocation(this.program, "position");
    this.gl.enableVertexAttribArray(position);
    this.gl.vertexAttribPointer(position, 2, this.gl.FLOAT, false, 0, 0);
  }

  setupTexture() {
    this.texture = this.gl.createTexture();
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => {
      this.gl.bindTexture(this.gl.TEXTURE_2D, this.texture);
      this.gl.texImage2D(
        this.gl.TEXTURE_2D,
        0,
        this.gl.RGBA,
        this.gl.RGBA,
        this.gl.UNSIGNED_BYTE,
        img
      );
      this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MIN_FILTER, this.gl.LINEAR);
      this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_S, this.gl.CLAMP_TO_EDGE);
      this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_T, this.gl.CLAMP_TO_EDGE);
    };
    img.src = this.imageUrl;
  }

  setupEventListeners() {
    this.canvas.addEventListener("mousemove", (e) => {
      this.mouse = [e.clientX, this.canvas.height - e.clientY];
    });

    window.addEventListener("resize", () => {
      this.setCanvasSize();
    });
  }

  render() {
    this.setCanvasSize();
    const currentTime = (performance.now() - this.startTime) / 1000;

    this.gl.viewport(0, 0, this.canvas.width, this.canvas.height);
    this.gl.clear(this.gl.COLOR_BUFFER_BIT);

    this.gl.uniform3f(this.uniforms.resolution, this.canvas.width, this.canvas.height, 1.0);
    this.gl.uniform1f(this.uniforms.time, currentTime);
    this.gl.uniform4f(this.uniforms.mouse, this.mouse[0], this.mouse[1], 0, 0);

    this.gl.activeTexture(this.gl.TEXTURE0);
    this.gl.bindTexture(this.gl.TEXTURE_2D, this.texture);
    this.gl.uniform1i(this.uniforms.texture, 0);

    this.gl.drawArrays(this.gl.TRIANGLE_STRIP, 0, 4);
    requestAnimationFrame(() => this.render());
  }
}
