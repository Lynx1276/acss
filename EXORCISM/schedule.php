<?php



?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Schedule Planner</title>
  
</head>

</head>
<body>
  <h2>Chairperson Scheduling Planner</h2>

  <!-- Selectors for Faculty and Subject -->
  <select id="faculty" class="draggable">
    <option value="">-- Select Faculty --</option>
  </select>

  <select id="subject" class="draggable">
    <option value="">-- Select Subject --</option>
  </select>

  <!-- Box to display selected Faculty and Subject for drag-and-drop -->
  <div id="draggableBox" class="draggable-box" draggable="true">
    <p>Drag me to the schedule!</p>
  </div>

  <!-- Schedule Table -->
  <table class="schedule-table" id="scheduleTable">
    <thead>
      <tr>
        <th>Time</th>
        <th>Monday</th>
        <th>Tuesday</th>
        <th>Wednesday</th>
        <th>Thursday</th>
        <th>Friday</th>
        <th>Saturday</th>
      </tr>
    </thead>
    <tbody id="scheduleBody"></tbody>
  </table>

  <button class="print-btn print-hide" onclick="window.print()">Print Schedule</button>

  <script>
    // Data for time slots and days
    const times = [
      { start: '7:30 AM', end: '8:30 AM' },
      { start: '8:30 AM', end: '9:30 AM' },
      { start: '9:30 AM', end: '10:30 AM' },
      { start: '10:30 AM', end: '11:30 AM' },
      { start: '11:30 AM', end: '12:30 PM' },
      { start: '12:30 PM', end: '1:30 PM' },
      { start: '1:30 PM', end: '2:30 PM' },
      { start: '2:30 PM', end: '3:30 PM' },
      { start: '3:30 PM', end: '4:30 PM' },
      { start: '4:30 PM', end: '5:30 PM' },
      { start: '5:30 PM', end: '6:00 PM' }
    ];
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    // Populate schedule grid dynamically
    const tbody = document.getElementById('scheduleBody');
    times.forEach(time => {
      const row = document.createElement('tr');
      const timeCell = document.createElement('td');
      timeCell.textContent = `${time.start} to ${time.end}`;
      row.appendChild(timeCell);
      days.forEach(day => {
        const cell = document.createElement('td');
        cell.dataset.time = time.start;
        cell.dataset.day = day;
        cell.classList.add('droppable');
        row.appendChild(cell);
      });
      tbody.appendChild(row);
    });

    // Fetch Faculty and Subject Data
    fetch('load_data.php?type=faculty')
      .then(response => response.json())
      .then(data => {
        const facultySelect = document.getElementById('faculty');
        data.forEach(faculty => {
          let option = document.createElement('option');
          option.value = faculty.id;
          option.textContent = faculty.name;
          facultySelect.appendChild(option);
        });
      });

    fetch('load_data.php?type=subject')
      .then(response => response.json())
      .then(data => {
        const subjectSelect = document.getElementById('subject');
        data.forEach(subject => {
          let option = document.createElement('option');
          option.value = subject.id;
          option.textContent = subject.label;
          subjectSelect.appendChild(option);
        });
      });

    // Update the draggable box text based on selection
    const facultySelect = document.getElementById('faculty');
    const subjectSelect = document.getElementById('subject');
    const draggableBox = document.getElementById('draggableBox');

    function updateDraggableBox() {
      const faculty = facultySelect.options[facultySelect.selectedIndex]?.text;
      const subject = subjectSelect.options[subjectSelect.selectedIndex]?.text;

      if (faculty && subject) {
        draggableBox.innerHTML = `<p><strong>${subject}</strong><br>Faculty: ${faculty}</p>`;
      }
    }

    facultySelect.addEventListener('change', updateDraggableBox);
    subjectSelect.addEventListener('change', updateDraggableBox);

    // Drag-and-Drop Logic for the Schedule
    document.querySelectorAll('.droppable').forEach(cell => {
      cell.addEventListener('dragover', function(e) {
        e.preventDefault(); // Allow drop
        cell.classList.add('over');
      });

      cell.addEventListener('dragleave', function() {
        cell.classList.remove('over');
      });

      cell.addEventListener('drop', function(e) {
        e.preventDefault();
        const faculty = facultySelect.value;
        const subject = subjectSelect.value;

        if (faculty && subject) {
          const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text; // Get the subject name
          const facultyName = facultySelect.options[facultySelect.selectedIndex].text; // Get the faculty name

          // Set the table cell content to show the subject and faculty
          cell.innerHTML = `<strong>${subjectName}</strong><br>${facultyName}`;
          cell.classList.remove('over');
        } else {
          alert('Please select both faculty and subject');
        }
      });
    });

    // Draggable text functionality
    draggableBox.addEventListener('dragstart', function(e) {
      const faculty = facultySelect.value;
      const subject = subjectSelect.value;

      if (faculty && subject) {
        // Pass the subject name and faculty name instead of just ID
        const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
        const facultyName = facultySelect.options[facultySelect.selectedIndex].text;

        e.dataTransfer.setData('text', `${subjectName} with ${facultyName}`);
      }
    });

    draggableBox.addEventListener('dragend', function() {
      draggableBox.style.opacity = 1; // Reset opacity after dragging
    });
  </script>

  <style>
    .draggable-box {
      width: 200px;
      height: 100px;
      border: 1px solid #ccc;
      padding: 10px;
      margin-top: 20px;
      background-color: #f9f9f9;
      cursor: move;
      text-align: center;
    }

    .over {
      background-color: #f0f0f0;
    }

    .droppable {
  width: 120px; /* Adjust to your preferred size */
  height: 60px; /* Adjust to your preferred size */
  overflow: hidden; /* Prevent content overflow */
  font-size: 12px;
}


    /* Styles for Print */
    @media print {
  body * {
    @page {
    size: A4 landscape; /* Landscape orientation */
    margin: 0; /* Remove default page margins */
  }
  body {
    margin: 0; /* Remove body margins for full use of space */
  }
    visibility: hidden;
    .droppable {
    font-size: 16px; /* Larger font for printing */
  }
  }
  .schedule-table, .schedule-table * {
    visibility: visible;
  }
  .schedule-table {
    width: 100%; /* Stretch table to fill the width of the paper */
    height: 100%; /* Stretch table to fill the height of the paper */
    table-layout: fixed; /* Ensure cells have fixed widths */
    border-collapse: collapse; /* Remove space between cells */
  }
  .schedule-table th, .schedule-table td {
    font-size: 10px; /* Adjust font size to fit content neatly */
    padding: 5px; /* Minimize padding for compact spacing */
    width: auto; /* Dynamically adjust column widths */
    height: auto; /* Dynamically adjust row heights */
  }



      .print-hide {
        display: none;
      }

      h2 {
        text-align: center;
        margin-bottom: 20px;
      }

      .schedule-table {
        width: 100%;
        border-collapse: collapse;
      }

      .schedule-table th, .schedule-table td {
        padding: 10px;
        border: 1px solid #000;
        text-align: center;
      }

      .schedule-table th {
        background-color: #f2f2f2;
      }
    }
  </style>
</body>

</html>
