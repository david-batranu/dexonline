(function(){

  if (!Detector.webgl) { Detector.addGetWebGLMessage(); };

  var container, stats, controls;
  var camera, scene, renderer;

  var clock = new THREE.Clock();

  init();

  function init() {
    container = document.getElementById('3deditor');
    camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 1, 2000);
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
      },
      onProgress, onError
    );

    // Renderer
    renderer = new THREE.WebGLRenderer();
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setClearColor(0x000000);
    container.appendChild(renderer.domElement);

    // Controls
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.target.set(0, 12, 0);
    camera.position.set(2, 18, 28);
    controls.update();

    // Lights
    var light = new THREE.AmbientLight(0xffffff);
    scene.add(light);

    // Resize
    window.addEventListener('resize', function() {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
    }, false)

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
