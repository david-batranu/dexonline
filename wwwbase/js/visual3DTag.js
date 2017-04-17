(function(){

  var RESOLUTION = [1024, 768];

  if (!Detector.webgl) { Detector.addGetWebGLMessage(); };

  var container, stats, controls;
  var camera, scene, renderer;

  var clock = new THREE.Clock();

  init();

  function init() {
    container = document.getElementById('3deditor');
    camera = new THREE.PerspectiveCamera(45, RESOLUTION[0] / RESOLUTION[1], 1, 2000);
    scene = new THREE.Scene();

    stats = new Stats();
    container.appendChild(stats.dom);

    var manager = new THREE.LoadingManager();
    manager.onProgress = function(item, loaded, total) {
      console.log(item, loaded, total);
    }

    var onProgress = function(xhr) {
      if (xhr.lengthComputable) {
        var percentComplete = xhr.loaded / xhr.total * 100;
        console.log(Math.round(percentComplete, 2) + '% downloaded');
      }
    }

    var onError = function(xhr) {
      console.log(xhr);
    };

    var loader = new THREE.JSONLoader(manager);
    loader.load(
      'http://dex.localhost/static/3dvisual/untitled.json',
      function(geometry, materials) {
        var mesh = new THREE.Mesh(geometry, materials);
        scene.add(mesh);
        controls.target.copy(mesh.position);

        var wireframe = new THREE.WireframeGeometry(geometry);
        var wireframe_mat = new THREE.LineBasicMaterial({
          color: 0xffffff,
          linewidth: 1,
        });
        var line = new THREE.LineSegments(wireframe, wireframe_mat);
        line.material.depthTest = false;
        line.material.opacity = 0.25;
        line.material.transparent = false;
        scene.add(line);

      },
      onProgress, onError
    );

    // Renderer
    renderer = new THREE.WebGLRenderer();
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(RESOLUTION[0], RESOLUTION[1]);
    renderer.setClearColor(0x000000);
    container.appendChild(renderer.domElement);

    // Controls
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    // controls.target.set(0, 12, 0);
    camera.position.set(0, 0, 10);
    controls.update();

    // Lights
    var light = new THREE.AmbientLight(0xffffff);
    scene.add(light);

    animate();

  }

  function animate() {
    requestAnimationFrame(animate);
    stats.update();
    render();
  }

  function render() {
    renderer.render(scene, camera);
  }

})()
