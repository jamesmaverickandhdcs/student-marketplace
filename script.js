// Register form validation
document.getElementById("registerForm")?.addEventListener("submit", function (e) {
    e.preventDefault();
    let username = document.getElementById("username").value;
    let email = document.getElementById("email").value;
    let password = document.getElementById("password").value;

    if (username.length < 3) {
        alert("Username must be at least 3 characters long.");
        return;
    }
    if (password.length < 6) {
        alert("Password must be at least 6 characters long.");
        return;
    }
    alert("Registration successful (simulation)!");
});

// Login form validation
document.getElementById("loginForm")?.addEventListener("submit", function (e) {
    e.preventDefault();
    let username = document.getElementById("loginUsername").value;
    let password = document.getElementById("loginPassword").value;

    if (username === "" || password === "") {
        alert("Please fill in all fields.");
        return;
    }
    alert("Login successful (simulation)!");
});

function welcomeMessage() {
    const message = document.createElement("p");
    message.textContent = "Thanks for visiting! Explore listings now.";
    document.querySelector("main").appendChild(message);
}
