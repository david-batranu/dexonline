$(function() {

  function init() {
    var selects = [].slice.call(document.getElementsByTagName('select'));
    selects.forEach(function(select){
      var value = select.getElementsByTagName('option')[0].getAttribute('value');
      $(select).select2({
        ajax: { url: wwwRoot + 'ajax/getEntries.php' },
        minimumInputLength: 1,
        placeholder: 'caută o intrare',
        width: '300px',
      }).change(console.log);
    });
  }

  init();
});


window.start3d = (function(){

  var RESOLUTION = [800, 600];

  if (!Detector.webgl) { Detector.addGetWebGLMessage(); };

  var container, controls;
  var camera, scene, renderer;

  var debug = document.getElementById('debug');

  var clock = new THREE.Clock();

  var MAT_HIGHLIGHT = new THREE.MeshPhongMaterial({
    color: new THREE.Color(1, 0, 0)
  });


  function init() {
    container = document.getElementById('3dpreview');
    camera = new THREE.PerspectiveCamera(45, RESOLUTION[0] / RESOLUTION[1], 1, 2000);
    scene = new THREE.Scene();

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

    var loader = new THREE.ObjectLoader(manager);
    loader.load(
      container.getAttribute('data-src'),
      function(object) {

        function wireframe_from_geo(geometry) {
          var wireframe_geo = new THREE.EdgesGeometry(geometry);
          var wireframe_mat = new THREE.LineBasicMaterial({
            color: 0xffffff,
            linewidth: 1,
          });
          var wireframe = new THREE.LineSegments(wireframe_geo, wireframe_mat);
          wireframe.material.depthTest = false;
          wireframe.visible = false;
          return wireframe;
        }

        var meshes = object.children.filter(function(c) { return c.type === 'Mesh' });
        meshes.forEach(function(child){
          var wireframe = wireframe_from_geo(child.geometry);
          child.add(wireframe);
          child.wireframe = wireframe;
          var tag = document.createElement('a');
          tag.setAttribute('href', '#');
          tag.textContent = child.name;
          tag.addEventListener('click', function(evt){
            evt.preventDefault();
            var obj = scene.getObjectByName(evt.target.textContent);
            if (obj.material_bak) {
              obj.material = obj.material_bak;
              obj.wireframe.visible = false;
              delete obj.material_bak;
            }
            else {
              obj.material_bak = obj.material;
              obj.material = MAT_HIGHLIGHT;
              obj.wireframe.visible = true;
            }
          });

          add_table_entry(child.name, tag);
        });

        scene.add(object);
        controls.target.copy(object.position);
      },
      onProgress, onError
    );

    // Renderer
    renderer = new THREE.WebGLRenderer();
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(RESOLUTION[0], RESOLUTION[1]);
    renderer.setClearColor(0xededed);
    container.appendChild(renderer.domElement);

    // Controls
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enablePan = false;
    camera.position.set(0, 0, 10);
    controls.update();

    // Lights
    // var light = new THREE.AmbientLight(0xffffff);
    // scene.add(light);

    animate();

  }

  function animate() {
    requestAnimationFrame(animate);
    render();
  }

  function render() {
    renderer.render(scene, camera);
  }

  function add_table_entry(name, elem) {
    var cell_name_exists = document.getElementById(name);
    if (cell_name_exists) {
      cell_name_exists.appendChild(elem);
    }
    else {
      var row = document.createElement('tr');
      var cell_name = document.createElement('td');
      var cell_assign = document.createElement('td');
      var assign_select = document.createElement('select');
      assign_select.setAttribute('name', 'mapping_' + name);
      var tbody = document.getElementById('table-assign').getElementsByTagName('tbody')[0];

      cell_name.appendChild(elem);
      cell_assign.appendChild(assign_select);

      row.appendChild(cell_name);
      row.appendChild(cell_assign);

      tbody.appendChild(row);

      $(assign_select).select2({
        ajax: { url: wwwRoot + 'ajax/getEntries.php' },
        minimumInputLength: 1,
        placeholder: 'caută o intrare',
        width: '300px',
      }).change(console.log);
    }

  }

  init();

});
