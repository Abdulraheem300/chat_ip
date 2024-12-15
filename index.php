<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat with Audio Calls</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      display: flex;
      flex-direction: column;
      height: 100vh;
      background-color: #f9f9f9;
    }

    #chat {
      flex-grow: 1;
      overflow-y: auto;
      padding: 10px;
      background: #f0f0f0;
      display: flex;
      flex-direction: column;
    }

    #chat div {
      margin: 5px 0;
      padding: 10px;
      border-radius: 10px;
      max-width: 70%;
      word-wrap: break-word;
    }

    #chat img {
      max-width: 100%;
      margin-top: 5px;
      border-radius: 10px;
    }

    #chat div:nth-child(even) {
      align-self: flex-end;
      background-color: #dcf8c6;
    }

    #chat div:nth-child(odd) {
      align-self: flex-start;
      background-color: #ffffff;
      border: 1px solid #ccc;
    }

    #input-area {
      display: flex;
      padding: 10px;
      background: #007bff;
      position: sticky;
      bottom: 0;
      gap: 5px;
    }

    #input-area input[type="text"] {
      flex-grow: 1;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 20px;
      font-size: 14px;
    }

    #input-area button, #input-area label {
      padding: 10px 15px;
      background-color: #28a745;
      color: white;
      border: none;
      cursor: pointer;
      text-align: center;
      font-size: 14px;
    }

    #input-area button:hover, #input-area label:hover {
      background-color: #218838;
    }

    #input-area input[type="file"] {
      display: none;
    }

    #audio-container {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 90%;
      background: rgba(0, 0, 0, 0.8);
      border-radius: 10px;
      padding: 20px;
      z-index: 1000;
      color: white;
      text-align: center;
    }

    #audio-container button {
      width: 100%;
      padding: 10px;
      background-color: #ff4d4d;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 10px;
    }

    #audio-container button:hover {
      background-color: #cc0000;
    }

    @media (max-width: 600px) {
      #chat {
        padding: 5px;
      }

      #input-area {
        flex-direction: column;
        gap: 10px;
        padding: 5px;
      }

      #input-area input[type="text"] {
        font-size: 12px;
        padding: 8px;
      }

      #input-area button, #input-area label {
        font-size: 12px;
        padding: 8px;
      }

      #chat div {
        max-width: 85%;
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <div id="chat"></div>
  <div id="audio-container">
    <p>Audio Call in Progress...</p>
    <button id="endCall">End Call</button>
  </div>
  <div id="input-area">
    <input type="text" id="messageInput" placeholder="Type your message...">
    <label for="imageInput">📷</label>
    <input type="file" id="imageInput" accept="image/*">
    <button id="sendButton">Send</button>
    <button id="startRecording">🎤</button>
    <button id="startCallButton">📞</button>
  </div>

  <script>
    const socket = new WebSocket('ws://192.168.88.3:5555'); // خادم WebSocket
    const chat = document.getElementById('chat');
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const imageInput = document.getElementById('imageInput');
    const startRecordingButton = document.getElementById('startRecording');
    const startCallButton = document.getElementById('startCallButton');
    const audioContainer = document.getElementById('audio-container');
    const endCallButton = document.getElementById('endCall');

    let peerConnection;
    let localStream;
    let mediaRecorder;
    let audioChunks = [];

    const config = {
      iceServers: [
        { urls: 'stun:stun.l.google.com:19302' } // خادم STUN
      ]
    };

    // طلب الإذن للإشعارات عند تحميل الصفحة
    if (Notification.permission !== "granted") {
      Notification.requestPermission().then(permission => {
        if (permission === "granted") {
          console.log("Notifications enabled");
        } else {
          console.log("Notifications denied");
        }
      });
    }

    // عرض الإشعار
    function showNotification(title, body) {
      if (Notification.permission === "granted") {
        const notification = new Notification(title, {
          body: body,
          icon: "https://cdn-icons-png.flaticon.com/512/727/727399.png",
        });
        const audio = new Audio("https://www.soundjay.com/button/beep-07.wav");
        audio.play();
        notification.onclick = () => {
          window.focus();
        };
      }
    }

    sendButton.addEventListener('click', () => {
      const message = messageInput.value;
      if (message) {
        socket.send(JSON.stringify({ type: 'text', message }));
        chat.innerHTML += `<div><strong>You:</strong> ${message}</div>`;
        messageInput.value = '';
      }
    });

    imageInput.addEventListener('change', () => {
      const file = imageInput.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const imageData = e.target.result;
          socket.send(JSON.stringify({ type: 'image', image: imageData }));
          chat.innerHTML += `<div><img src="${imageData}" alt="Sent Image"></div>`;
        };
        reader.readAsDataURL(file);
        imageInput.value = '';
      }
    });

    startRecordingButton.addEventListener('mousedown', async () => {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaRecorder = new MediaRecorder(stream);
      audioChunks = [];
      mediaRecorder.ondataavailable = event => audioChunks.push(event.data);
      mediaRecorder.onstop = () => {
        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
        const reader = new FileReader();
        reader.onload = e => {
          socket.send(JSON.stringify({ type: 'audio', audio: e.target.result }));
          chat.innerHTML += `<div><audio controls src="${e.target.result}"></audio></div>`;
        };
        reader.readAsDataURL(audioBlob);
      };
      mediaRecorder.start();
    });

    startRecordingButton.addEventListener('mouseup', () => {
      mediaRecorder.stop();
    });

    socket.onmessage = (event) => {
      const data = JSON.parse(event.data);

      if (data.type === "text") {
        chat.innerHTML += `<div>${data.message}</div>`;
        showNotification("New Message", data.message);
      } else if (data.type === "image") {
        chat.innerHTML += `<div><img src="${data.image}" alt="Received Image"></div>`;
        showNotification("New Image", "You received an image!");
      } else if (data.type === "audio") {
        chat.innerHTML += `<div><audio controls src="${data.audio}"></audio></div>`;
        showNotification("New Audio", "You received an audio message!");
      }
    };

    startCallButton.addEventListener('click', async () => {
      audioContainer.style.display = 'block';
      localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      peerConnection = new RTCPeerConnection(config);
      localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
      peerConnection.onicecandidate = event => {
        if (event.candidate) {
          socket.send(JSON.stringify({ type: 'candidate', candidate: event.candidate }));
        }
      };
      const offer = await peerConnection.createOffer();
      await peerConnection.setLocalDescription(offer);
      socket.send(JSON.stringify({ type: 'offer', offer }));
    });

    endCallButton.addEventListener('click', () => {
      peerConnection.close();
      audioContainer.style.display = 'none';
    });
  </script>
</body>
</html>
