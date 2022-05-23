var socket = io.connect(window.location.href, {});

  socket.connect();
  var formData;
  //Login to server using api key through POST
  $(document).ready(function(){
    $('#login-form i').hide();
    $('.fa-user, .fa-lock').show();
    $('#email, #password').on('keydown', function(){
        $(this).removeClass("invalid");
        $(this).siblings('i').hide();
        $('.fa-user, .fa-lock').show();
    });

    $('#password').on('keydown', function(){
        $('#password').removeClass("invalid");
        $('#password').siblings('i').hide();
        $('.fa-user, .fa-lock').show();
    });

    $('#eye-pass').on('click', function() {
        if ($('#password').attr('type') == "password") {
            $('#password').attr('type', 'text');
        }
        else if ($('#password').attr('type') == "text") {
            $('#password').attr('type', 'password');
        }
    });

  $("#login-form").on("submit", () => {
      document.getElementById("socketID").value = socket.id;
      formData = $('#login-form').serialize();
      $.ajax({
          url:'/login',
          type:'post',
          data: $('#login-form').serialize()
      });
      return false;
  });
  });

  socket.on("login-failed",function(){
      createModal("Login Faiedl", "Please check your username / password.","");
  });


  var apikey = false;

  socket.on("login-success",(apiKey) => {
      $(".modal").remove();
      apikey = apiKey;
      socket.emit("authorise", apiKey);
      $(".hero-posters").remove();
      $("#reg-container").fadeOut("slow",function(){
          var articles = document.createElement("div");
          articles.classList.add("articles");
          articles.style.display = "none";
         
          document.getElementsByTagName("body")[0].appendChild(articles);

          $(articles).fadeIn("slow");

      });    
  });

  socket.on('load', (src, index) => {
      if(index != 0){
          var chat = index;

          createContentModal("","<div id='chat-box'>  </div>");
          setTimeout(() => {
              $(".modal-footer h3").fadeOut("slow");
          }, 1500);
      } else {
          createContentModal("","<div id='chat-box","");
      }
  });

      
  var reconn;
  var timeout;

  socket.on('bye', function(){
      createModal("Connection Lost", "Lost connection to the server.","Reconnectiong...");

      if(!reconn)
      reconn = setInterval(() => {
          socket.connect();
      }, 1000);

      if(!timeout)
      timeout = setTimeout(() => {
          document.getElementsByTagName("body")[0].innerHTML = "";
          createModal("Reconnect Failed","Can't reach server","Please refresh.");
          clearInterval(reconn);
      }, 15000);
  });
  
  socket.on("disconnect", function(){
      createModal("Connection Lost", "The connection to the server has been lost.","Attempting to reconnect...");

      if(!reconn)
      reconn = setInterval(() => {
          socket.connect();
      }, 1000);

      if(!timeout)
      timeout = setTimeout(() => {
          document.getElementsByTagName("body")[0].innerHTML = "";
          createModal("Reconnect Failed","The server is unreachable, please try to refresh the page.","");
          clearInterval(reconn);
      }, 10000);
  })

  socket.on('connect',function(){

      clearTimeout(timeout);
      clearInterval(reconn);
      timeout = undefined;
      reconn = undefined;

      $(".modal").remove();

      //was logged in before server lost connection
      if(apikey != false){
       createModal("Authenticating","Logging in...","");
        var n = formData.indexOf("socketID");
        formData = formData.substring(0,n);
        formData = formData + "socketID="+socket.id;
          $.ajax({
              url:'/login',
              type:'post',
              data: formData
          });
      }
  })

function createModal(header, body, footer){

  $(".modal").remove();
  
  var modalParent = document.createElement("div");
  modalParent.classList.add("modal");
  modalParent.id = "modal"
  
  var modal = document.createElement("div");
  modal.classList.add("modal-content");

  
  var modalHeader = document.createElement("div");
  modalHeader.classList.add("modal-header");
  
  var closebtn = document.createElement("span");
  closebtn.classList.add("close");
  closebtn.innerHTML = "&times;";
  
  var headerText = document.createElement("h2");
  headerText.innerHTML = header;
  
  modalHeader.appendChild(closebtn);
  modalHeader.appendChild(headerText);
  
  modal.appendChild(modalHeader);
  
  var modalBody = document.createElement("div");
  modalBody.classList.add("modal-body");
  
  var bodyText = document.createElement("p");
  bodyText.innerHTML = body;
  
  modalBody.appendChild(bodyText);
  modal.appendChild(modalBody);
  
  var modalFooter = document.createElement("div");
  modalFooter.classList.add("modal-footer");
  
  var footerText = document.createElement("h3");
  footerText.innerHTML = footer;
  
  modalFooter.appendChild(footerText);
  
  modal.appendChild(modalFooter);
  
  modalParent.appendChild(modal);
  
  document.getElementsByTagName("body")[0].appendChild(modalParent);
  
  var modal = document.getElementById('modal');
  
  var span = document.getElementsByClassName("close")[0];
  
  span.onclick = function() {
      modal.style.display = "none";
      document.getElementsByTagName("body")[0].removeChild(modal);
  }
  
  window.onclick = function(event) {
  if (event.target == modal) {
      modal.style.display = "none";
      document.getElementsByTagName("body")[0].removeChild(modal);
      }
  }
  
  modal.style.display = "block";
}

const messageContainer = document.getElementById('message-container')
const messageForm = document.getElementById('send-container')
const messageInput = document.getElementById('message-input')

console.log(`${name} joined`);
socket.emit('new-user', name)

socket.on('chat-message', data => {
  appendMessage(`${data.name}: ${data.message}`)
})

socket.on('user-connected', name => {
  appendMessage(`${name} connected`)
  console.log(`${name} connected`)
})

socket.on('user-disconnected', name => {
  appendMessage(`${name} disconnected`)
  console.log(`${name} disconnected`);
})

messageForm.addEventListener('submit', e => {
  e.preventDefault()
  const message = messageInput.value
  appendMessage(`You: ${message}`)
  socket.emit('send-chat-message', message)
  messageInput.value = ''
})

function appendMessage(message) {
  const messageElement = document.createElement('div')
  messageElement.innerText = message
  messageContainer.append(messageElement)
}


function createContentModal(header, body, footer){
  
  var modalParent = document.createElement("div");
  modalParent.classList.add("modal");
  modalParent.id = "modal"
  
  var modal = document.createElement("div");
  modal.classList.add("modal-content");

  
  var modalHeader = document.createElement("div");
  modalHeader.classList.add("modal-header");
  
  var closebtn = document.createElement("span");
  closebtn.classList.add("close");
  closebtn.innerHTML = "&times;";
  
  var headerText = document.createElement("h2");
  headerText.innerHTML = header;
  
  modalHeader.appendChild(closebtn);
  modalHeader.appendChild(headerText);
  
  modal.appendChild(modalHeader);
  
  var modalBody = document.createElement("div");
  modalBody.classList.add("modal-body");
  
  var bodyText = document.createElement("div");
  bodyText.classList.add("bodyText");
  bodyText.innerHTML = body;
  
  modalBody.appendChild(bodyText);
  modal.appendChild(modalBody);
  
  var modalFooter = document.createElement("div");
  modalFooter.classList.add("modal-footer");
  
  var footerText = document.createElement("h3");
  footerText.innerHTML = footer;
  
  modalFooter.appendChild(footerText);
  
  modal.appendChild(modalFooter);
  
  modalParent.appendChild(modal);
  
  document.getElementsByTagName("body")[0].appendChild(modalParent);
  
  var modal = document.getElementById('modal');
  
  var span = document.getElementsByClassName("close")[0];
  
  modal.style.display = "block";
}

function base64(e) {
    var t = "";
    var n = new Uint8Array(e);
    var r = n.byteLength;
    for (var i = 0; i < r; i++) {
        t += String.fromCharCode(n[i]);
    }
    return window.btoa(t)
}

function loginValidate(e) {
    // validate email

    // TODO: Error msg
    var regEmail = /^(([^<>()\[\]\\/\-%&#?\^.,;:\s@]+(\.[^<>()\[\]\\/\-%&#?\^.,;:\s@]+)*)|('.+'))@(([a-zA-Z0-9]+\.)+[a-zA-Z]{2,3})$/;
    if (!regEmail.test($('#email').val())) {
        e.preventDefault();
        $('#email').focus();
        $('#email').addClass("invalid");
        $('#email').siblings('i').show();
        // return false;
    }
    else {
        $('#email').removeClass("invalid");
        $('#email').siblings('i').hide();
        $('.fa-user, .fa-lock').show();
    }

    // validate password && check password match

    // TODO: Error msg
    var regPass = /^(?=.*[0-9])(?=.*[-!@#\$%\^&\*])(?=.*[a-z])(?=.*[A-Z])(?=.{9,})/;
    if (!regPass.test($('#password').val())) {
        $('#password').focus();
        $('#password').addClass("invalid");
        $('#password').siblings('i').show();
        $('.eye').css("margin-left", "-70px");
        e.preventDefault();
        // return false;
    }
    else {
        $('#password').removeClass("invalid");
        $('#password').siblings('i').hide();
        $('.fa-user, .fa-lock').show();
    }

    // return true;
}