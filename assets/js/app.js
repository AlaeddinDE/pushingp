async function loadMembers() {
  const res = await fetch('api/members.php');
  const members = await res.json();
  console.table(members);
}
loadMembers();
