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

  var RESOLUTION = {w: 800, h: 600};

  if (!Detector.webgl) { Detector.addGetWebGLMessage(); };

  var container, controls;
  var camera, scene, renderer;

  var debug = document.getElementById('debug');

  var clock = new THREE.Clock();

  var MESHES;
  var UI = document.getElementById('overlay-2d').getElementsByTagName('canvas')[0].getContext('2d');

  var MAT_HIGHLIGHT = new THREE.MeshPhongMaterial({
    color: new THREE.Color(1, 0, 0)
  });


  window.toScreen = function(obj) {
    var box = new THREE.Box3();
    box.setFromObject(obj);

    var pos = box.getCenter().clone();
    // pos = pos.setFromMatrixPosition(obj.matrixWorld);
    pos.project(camera);

    var widthHalf = RESOLUTION.w / 2;
    var heightHalf = RESOLUTION.h / 2;

    pos.x = (pos.x * widthHalf) + widthHalf;
    pos.y = - (pos.y * heightHalf) + heightHalf;
    pos.z = 0;

    return [pos.x, pos.y, obj];

    console.log(pos);
  }

  function init(loadCallback) {
    container = document.getElementById('preview-3d');
    camera = new THREE.PerspectiveCamera(45, RESOLUTION.w / RESOLUTION.h, 1, 2000);
    scene = new THREE.Scene();

    function clone_event(evt) {
      var new_evt;
      if (evt.type === 'wheel') {
        new_evt = new WheelEvent(evt.type, evt);
        evt.preventDefault();
      } else {
        new_evt = new MouseEvent(evt.type, evt);
      };
      container.getElementsByTagName('canvas')[0].dispatchEvent(new_evt);
    }

    ['mousedown', 'mouseenter', 'mouseleave', 'mousemove', 'mouseout', 'mouseover', 'mouseup', 'scroll', 'wheel']
      .forEach(function(evt_type){
        UI.canvas.addEventListener(evt_type, clone_event);
      });


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

        MESHES = object.children.filter(function(c) { return c.type === 'Mesh' });
        MESHES.forEach(function(child){
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
        loadCallback();
      },
      onProgress, onError
    );

    // Renderer
    renderer = new THREE.WebGLRenderer();
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(RESOLUTION.w, RESOLUTION.h);
    renderer.setClearColor(0xededed);
    container.appendChild(renderer.domElement);

    // Controls
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enablePan = false;
    camera.position.set(0, 0, 20);
    controls.update();

    // var axisHelper = new THREE.AxisHelper( 5 );
    // scene.add( axisHelper );

    // Lights
    // var light = new THREE.AmbientLight(0xffffff);
    // scene.add(light);

    animate();

  }

  function animate() {
    UI.clearRect(0, 0, 800, 600);
    if (MESHES && UI && window.toScreen) {
      var centers = MESHES.map(window.toScreen).sort(function(a, b) {
        return a[1] - b[1];
      });
      var cc = {x: RESOLUTION.w / 2, y: RESOLUTION.h / 2}; // canvas center
      centers.forEach(function(xy, idx) {
        var mesh = xy[2];


	var mc = {x: xy[0] + 1, y: xy[1] + 1}; // mesh center

	var ray = {
	  a: mc.y - cc.y,
	  b: cc.x - mc.x,
	  c: cc.y * (mc.x - cc.x) - cc.x * (mc.y - cc.y)
	} // line through cc and mc


	var inters = [];
	if (ray.b) {
	  inters.push({x: 0, y: -ray.c / ray.b});
	  inters.push({x: RESOLUTION.w, y: (-ray.c - ray.a * RESOLUTION.w) / ray.b});
	}

	if (ray.a) {
	  inters.push({y: 0, x: -ray.c / ray.a});
	  inters.push({y: RESOLUTION.h, x: (-ray.c - ray.b * RESOLUTION.h) / ray.a});
	}

	if (!inters.length) { // can happen if mc == cc
	  inters.push({x: 0, y: 0});
	}

	function dist_sq(p1, p2) {
	  return (p2.x - p1.x) ** 2 + (p2.y - p1.y) ** 2;
	}

	var min_inter = inters.reduce(function(acc, point) {
	  if (!acc) { return point };
	  if (dist_sq(mc, acc) < dist_sq(mc, point)) { return acc }
	  return point;
	}, null);

	console.log('a', min_inter, 'b', mc, 'c', cc, 'd', inters);
	var tag = {
	  x: (min_inter.x + mc.x) / 2,
	  y: (min_inter.y + mc.y) / 2
	}

        UI.beginPath();
        UI.moveTo(tag.x, tag.y);
        UI.lineTo(mc.x, mc.y);
        UI.lineWidth = 0.5;
        UI.stroke();
        UI.font = "12px Arial";
        UI.fillText(mesh.name, tag.x, tag.y);
      });
    }
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
      var cell_camera = document.createElement('td');

      var assign_select = document.createElement('select');
      assign_select.setAttribute('name', 'mapping_' + name);

      var elem_camera_input = document.createElement('input');
      elem_camera_input.setAttribute('type', 'hidden');
      elem_camera_input.setAttribute('id', 'camera_' + name);
      elem_camera_input.setAttribute('name', 'camera_' + name);
      elem_camera_input.setAttribute('value', encodeVector(camera.position));

      function factory_camera_button(action, name, text) {
        var elem = document.createElement('button');
        elem.setAttribute('data-action', action);
        elem.setAttribute('data-mesh', name);
        elem.setAttribute('class', 'btn btn-sm btn-default');
        elem.textContent = text;
        return elem;
      }

      elem_camera_save = factory_camera_button('save-camera', name, 'Salvează poziția camerei');
      elem_camera_restore = factory_camera_button('restore-camera', name, 'Aplică poziția camerei');

      var tbody = document.getElementById('table-assign').getElementsByTagName('tbody')[0];

      cell_name.appendChild(elem);
      cell_assign.appendChild(assign_select);

      cell_camera.appendChild(elem_camera_input);
      cell_camera.appendChild(elem_camera_save);
      cell_camera.appendChild(elem_camera_restore);

      row.appendChild(cell_name);
      row.appendChild(cell_assign);
      row.appendChild(cell_camera);

      tbody.appendChild(row);

      $(assign_select).select2({
        ajax: { url: wwwRoot + 'ajax/getEntries.php' },
        minimumInputLength: 1,
        placeholder: 'caută o intrare',
        width: '300px',
      }).change(console.log);
    }

  }

  function init_camera_save() {
    var buttons = [].slice.call(document.querySelectorAll('[data-action="save-camera"]'));
    buttons.forEach(function(button) {
      button.addEventListener('click', function(evt) {
        evt.preventDefault();
        var mesh = button.getAttribute('data-mesh');
        var target = document.getElementById('camera_' + mesh);
        target.setAttribute('value', encodeVector(camera.position));
      });
    });
  }

  function init_camera_restore() {
    var buttons = [].slice.call(document.querySelectorAll('[data-action="restore-camera"]'));
    buttons.forEach(function(button) {
      button.addEventListener('click', function(evt) {
        evt.preventDefault();
        var mesh = button.getAttribute('data-mesh');
        var target = document.getElementById('camera_' + mesh);
        var saved = decodeVector(target.getAttribute('value'));
        camera.position.set(saved.x, saved.y, saved.z);
        controls.update();
      });
    });
  }

  function init_default_cameras() {
    var inputs = [].slice.call(document.querySelectorAll('[id*="camera_"]'));
    var camera_position = encodeVector(camera.position);
    inputs.forEach(function(input){
      if(input.getAttribute('value') === '') {
        input.setAttribute('value', camera_position)
      }
    });
  }

  function encodeVector(value) {
    return [value.x, value.y, value.z].join(',')
  }

  function decodeVector(value) {
    var arr = value.split(',').map(parseFloat);
    return new THREE.Vector3(arr[0], arr[1], arr[2])
  }

  init(function(){
    init_default_cameras();
    init_camera_save();
    init_camera_restore();
  });

});
