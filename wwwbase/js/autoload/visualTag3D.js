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

  var config = {
    resolution: {
      w: 800,
      h: 600
    },
  };

  var globals = {
    camera: new THREE.PerspectiveCamera(45, config.resolution.w / config.resolution.h, 1, 2000),
    scene: new THREE.Scene(),
    container: document.getElementById('preview-3d'),
    clock: new THREE.Clock(),
    center: { // canvas center
      x: config.resolution.w / 2,
      y: config.resolution.h / 2
    },
    ui: function(){
      return document.getElementById('overlay-2d')
        .getElementsByTagName('canvas')[0]
        .getContext('2d');
    }(),
    objects: [],
    renderer: null,
    controls: null,

    container_json: document.getElementById('jsondata'),
    json: {}
  };

  var materials = {
    highlight: new THREE.MeshPhongMaterial({
      color: new THREE.Color(1, 0 ,0)
    }),
    wireframe: new THREE.LineBasicMaterial({
      color: 0xffffff,
      linewidth: 1,
    })
  };

  var clone_events = [
    {
      events: [
        'mousedown',
        'mouseenter',
        'mouseleave',
        'mousemove',
        'mouseout',
        'mouseover',
        'mouseup',
        'scroll',
      ],
      trigger: MouseEvent
    },
    {
      events: ['wheel'],
      trigger: WheelEvent
    }
  ];


  var RESOLUTION = config.resolution;
  var UI = globals.ui;

  if (!Detector.webgl) { Detector.addGetWebGLMessage(); };

  var utils = (function(){
    function projectToScreen(obj, camera, resolution){
      var box = new THREE.Box3();
      box.setFromObject(obj);

      var pos = box.getCenter().clone();
      pos.project(camera);

      var widthHalf = resolution.w / 2;
      var heightHalf = resolution.h / 2;

      pos.x = (pos.x * widthHalf) + widthHalf;
      pos.y = - (pos.y * heightHalf) + heightHalf;
      pos.z = 0;

      return {
        x: pos.x,
        y: pos.y,
        obj: obj
      };
    }

    function tagFromPoint(
      point /* object coordinates */,
      cc /* canvas center */,
      resolution
    ) {
      var pc = { // point center
        x: point.x + 1,
        y: point.y + 1
      }; // adding 1 to avoid points at 0, 0

      var ray = {
        a: pc.y - cc.y,
        b: cc.x - pc.x,
        c: cc.y * (pc.x - cc.x) - cc.x * (pc.y - cc.y)
      } // line through cc and pc


      var inters = [];
      if (ray.b) {

        inters.push({
          x: 0,
          y: -ray.c / ray.b
        });

        inters.push({
          x: resolution.w,
          y: (-ray.c - ray.a * resolution.w) / ray.b
        });

      }

      if (ray.a) {

        inters.push({
          x: -ray.c / ray.a,
          y: 0
        });

        inters.push({
          x: (-ray.c - ray.b * resolution.h) / ray.a,
          y: resolution.h
        });

      }

      if (!inters.length) { // can happen if pc == cc
        inters.push({x: 0, y: 0});
      }

      function dist_sq(p1, p2) {
        return (p2.x - p1.x) ** 2 + (p2.y - p1.y) ** 2;
      }

      var min_inter = inters.reduce(function(acc, p) {
        if (!acc) {
          return p
        };

        if (dist_sq(pc, acc) < dist_sq(pc, p)) {
          return acc
        };

        return p;
      }, null);

      var tag = {
        x: (min_inter.x + pc.x) / 2,
        y: (min_inter.y + pc.y) / 2
      }

      return tag;
    };

    function assetLoader(url, callback) {
      var manager = new THREE.LoadingManager();

      var onError = function(xhr) {
        console.log(xhr);
      };

      var onProgress = function(xhr) {
        if (xhr.lengthComputable) {
          var percentComplete = xhr.loaded / xhr.total * 100;
          console.log(Math.round(percentComplete, 2) + '% downloaded');
        }
      }

      var loader = new THREE.ColladaLoader(manager);
      loader.load(url, callback, onProgress, onError)

    };

    function wireframeFromGeometry(geometry, wireframeMaterial) {
      var wireframeGeometry = new THREE.EdgesGeometry(geometry);
      var wireframe = new THREE.LineSegments(wireframeGeometry, wireframeMaterial);
      wireframe.material.depthTest = false;
      wireframe.visible = false;
      return wireframe;
    }

    function huntForMeshes(objects, result) {
      objects.forEach(function(obj) {
        if (obj.children) {
          huntForMeshes(obj.children, result);
        }
        if (obj.type === 'Mesh') {
          result.push(obj);
        }
      });
    };

    function renderTag(center, tag, name, ui) {
      ui.beginPath();
      ui.moveTo(tag.x, tag.y);
      ui.lineTo(center.x, center.y);
      ui.lineWidth = 0.5;
      ui.stroke();
      ui.font = "12px Arial";
      ui.fillText(name, tag.x, tag.y);
    };

    function setupRenderer(resolution, container) {
      renderer = new THREE.WebGLRenderer();
      renderer.setPixelRatio(window.devicePixelRatio);
      renderer.setSize(RESOLUTION.w, RESOLUTION.h);
      renderer.setClearColor(0xededed);
      return renderer;
    }

    return {
      projectToScreen: projectToScreen,
      tagFromPoint: tagFromPoint,
      assetLoader: assetLoader,
      wireframeFromGeometry: wireframeFromGeometry,
      huntForMeshes: huntForMeshes,
      renderTag: renderTag,
      setupRenderer: setupRenderer
    }

  })();


  function init(loadCallback) {

    // pass events to 3d canvas
    clone_events.forEach(function(def) {
      def.events.forEach(function(evt_type) {
        UI.canvas.addEventListener(evt_type, function(evt){
          var new_evt = new def.trigger(evt.type, evt);
          globals.container.getElementsByTagName('canvas')[0].dispatchEvent(new_evt);
        });
      });
    });


    function gameLoop(config, globals, utils) {
      var renderer = globals.renderer;
      var scene = globals.scene;
      var camera = globals.camera;

      function render() {
        renderer.render(scene, camera);
      }

      function update() {
        // clear ui canvas
        UI.clearRect(0, 0, config.resolution.w, config.resolution.h);

        // render tags
        var projectToScreen = function(obj) {
          return utils.projectToScreen(obj, globals.camera, config.resolution);
        };
        if (globals.objects.length > 0 && globals.ui && utils.projectToScreen) {
          var centers = globals.objects.map(projectToScreen);
          centers.forEach(function(center, idx) {
            var tag = utils.tagFromPoint(center, globals.center, config.resolution);
            var name = center.obj.name ? center.obj.name : center.obj.parent.name;
            utils.renderTag(center, tag, name, globals.ui);
          });
        }

        // run forever
        requestAnimationFrame(update);
        render();
      }

      update();

    }

    // load scene
    var url = globals.container.getAttribute('data-src');

    utils.assetLoader(url, function(collada){
      var object = collada.scene;

      globals.objects = function() {
        var result = [];
        utils.huntForMeshes(object.children, result);
        return result;
      }();

      globals.objects.forEach(function(child){
        var wireframe = utils.wireframeFromGeometry(child.geometry);
        child.add(wireframe);
        child.wireframe = wireframe;
      });

      globals.scene.add(object);

      // setup renderer
      globals.renderer = utils.setupRenderer(config.resolution);
      globals.container.appendChild(globals.renderer.domElement);

      // setup controls
      globals.camera.position.set(0, 0, 20);

      controls = new THREE.OrbitControls(globals.camera, globals.renderer.domElement);
      controls.target.copy(object.position);
      controls.enablePan = false;
      controls.update();

      globals.controls = controls;

      // ambient light
      // var light = new THREE.AmbientLight(0xffffff);
      // globals.scene.add(light);

      gameLoop(config, globals, utils);
      loadCallback(globals);

    });
  }

  function prepareJSON(globals) {
    var result = {
      meshes: {},
      json: {}
    };

    var jsondata = globals.container_json.value;
    result.json = JSON.parse(jsondata) || {};

    globals.objects.forEach(function(mesh){
      var mesh_name = mesh.name || mesh.parent.name;
      result.meshes[mesh_name] = mesh;

      if (!result.json[mesh_name]) {
       result.json[mesh_name] = {
          label: '',
          word: {
            id: null,
            label: null
          },
          camera: ''
        }
      }

    });

    return result;
  }

  function renderFields(globals) {
    var tbody = document
      .getElementById('table-assign')
      .getElementsByTagName('tbody')[0];

    for (name in globals.json.json) {
      createRowElement(globals, name, tbody);
    }
  }

  function createRowElement(globals, name, tbody) {
    var row = document.createElement('tr');
    var cell_name = document.createElement('td');
    var cell_assign = document.createElement('td');
    var cell_camera = document.createElement('td');

    var assign_select = document.createElement('select');
    var assign_word = globals.json.json[name].word;
    if (assign_word.id) {
      var option = document.createElement('option');
      option.value = assign_word.id;
      option.textContent = assign_word.label;
      assign_select.appendChild(option);
    }

    var button_clear = document.createElement('button');
    button_clear.className = 'glyphicon glyphicon-trash btn btn-sm btn-danger';

    button_clear.addEventListener('click', function(evt) {
      evt.preventDefault();
      $(assign_select).val(null).trigger('change');
      updateJSONData(globals);
    });

    var mesh = globals.json.meshes[name];
    cell_name.appendChild(createTagElement(name, mesh));
    cell_assign.appendChild(assign_select);
    cell_assign.appendChild(button_clear);

    createCameraButtons(name, globals).forEach(function(btn){
      cell_camera.appendChild(btn);
    });

    row.appendChild(cell_name);
    row.appendChild(cell_assign);
    row.appendChild(cell_camera);

    tbody.appendChild(row);

    $(assign_select).select2({
      ajax: { url: wwwRoot + 'ajax/getEntries.php' },
      minimumInputLength: 1,
      placeholder: 'caută o intrare',
      width: '300px',
    }).change(function(evt){
      var data = $(evt.target).select2('data')[0] || {id: '', text: ''};
      globals.json.json[name].word.id = data.id;
      globals.json.json[name].word.label = data.text;
      updateJSONData(globals);
    });

  }

  function createTagElement(name, mesh) {
    var tag = document.createElement('a');
    tag.setAttribute('href', '#');
    tag.textContent = name;
    tag.addEventListener('click', function(evt){
      evt.preventDefault();
      if (mesh.material_bak) {
        mesh.material = mesh.material_bak;
        mesh.wireframe.visible = false;
        delete mesh.material_bak;
      }
      else {
        mesh.material_bak = mesh.material;
        mesh.material = materials.highlight;
        mesh.wireframe.visible = true;
      }
    });
    return tag;
  }

  function createCameraButtons(name, globals) {

    function factory_camera_button(text) {
      var elem = document.createElement('button');
      elem.setAttribute('class', 'btn btn-sm btn-default');
      elem.textContent = text;
      return elem;
    }

    elem_camera_save = factory_camera_button('Salvează poziția camerei');
    elem_camera_restore = factory_camera_button('Aplică poziția camerei');

    elem_camera_save.addEventListener('click', function(evt) {
      evt.preventDefault();
      globals.json.json[name].camera = encodeVector(globals.camera.position);
      updateJSONData(globals);
    })

    elem_camera_restore.addEventListener('click', function(evt) {
      evt.preventDefault();
      var saved = decodeVector(globals.json.json[name].camera);
      globals.camera.position.set(saved.x, saved.y, saved.z);
      globals.controls.update();
    })

    return [
      elem_camera_save,
      elem_camera_restore
    ]
  }

  function updateJSONData(globals) {
    globals.container_json.value = JSON.stringify(globals.json.json);
  }

  function encodeVector(value) {
    return [value.x, value.y, value.z].join(',')
  }

  function decodeVector(value) {
    var arr = value.split(',').map(parseFloat);
    return new THREE.Vector3(arr[0], arr[1], arr[2])
  }

  init(function(globals){
    globals.json = prepareJSON(globals);
    updateJSONData(globals);
    renderFields(globals);
  });

});
