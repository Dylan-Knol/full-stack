let login = document.getElementById("inlogButton");
login.addEventListener("click", checkLogin);

function checkLogin(event) {
  event.preventDefault();
  let username = document.getElementById("inlogUsername").value;
  let password = document.getElementById("inlogPassword").value;
  let foutmelding = document.getElementById("foutmelding");
  if (username == "Dylan" && password == "Goedemorgen123") {
    foutmelding.style.color = "green";
    foutmelding.textContent = "Ingelogd! Welkom";
  } else {
    foutmelding.style.color = "red";
    foutmelding.textContent =
      "Gebruikersnaam of wachtwoord is onjuist. Probeer het opnieuw.";
  }
}