const fs = require('fs');
const path = 'C:\\Users\\nguye\\.gemini\\antigravity-ide\\brain\\4401ece3-6b51-4453-b583-a4eda2ef531b\\.system_generated\\logs\\transcript.jsonl';
const lines = fs.readFileSync(path, 'utf8').split('\n');

let targetContent1 = null;
let targetContent2 = null;

for (let i = lines.length - 1; i >= 0; i--) {
  if (!lines[i].trim()) continue;
  try {
    const step = JSON.parse(lines[i]);
    if (step.type === 'TOOL_RESPONSE' && step.tool_calls) {
      for (const call of step.tool_calls) {
        if (call.name === 'default_api:view_file' && call.response && call.response.output && call.response.output.includes('OwnerVenuePosts.vue')) {
           const out = call.response.output;
           if (out.includes('Showing lines 1 to 800')) {
               targetContent1 = out;
           }
           if (out.includes('Showing lines 800 to 1350')) {
               targetContent2 = out;
           }
        }
      }
    }
  } catch (e) {
    // ignore parse errors
  }
}

if (targetContent1 && targetContent2) {
  let allLines = [];
  const addLines = (text) => {
      const textLines = text.split('\n');
      for (const line of textLines) {
          const match = line.match(/^(\d+): (.*)/);
          if (match) {
              const num = parseInt(match[1]);
              allLines[num] = match[2];
          }
      }
  };
  addLines(targetContent1);
  addLines(targetContent2);
  
  // Also add the delete_confirm_modal that was missing!
  // I will inject it after line 373: `    </dialog>`
  const modalHTML = `
    <!-- Delete Confirmation Modal -->
    <dialog id="delete_confirm_modal" class="modal-dialog delete-dialog">
      <div class="modal-panel">
        <header>
          <h2>Xác nhận xóa</h2>
          <button type="button" @click="closeDeleteModal" class="btn-close" aria-label="Đóng"></button>
        </header>
        <div class="delete-content">
          <p>Bạn có chắc chắn muốn xóa bài viết <strong>"{{ deletingPost?.title }}"</strong>?</p>
          <p class="text-danger">Lưu ý: Bài viết sẽ bị ẩn khỏi hệ thống. Bạn không thể hoàn tác hành động này.</p>
        </div>
        <footer>
          <button type="button" class="btn secondary" @click="closeDeleteModal">
            Hủy bỏ
          </button>
          <button type="button" class="btn danger" @click="executeDelete">
            Xác nhận xóa
          </button>
        </footer>
      </div>
    </dialog>
  `;
  
  const finalCodeLines = allLines.slice(1);
  
  // Inject modalHTML
  const insertIndex = finalCodeLines.findIndex(line => line === '    </dialog>' && finalCodeLines[finalCodeLines.indexOf(line) + 1] === '  </div>');
  if (insertIndex !== -1) {
      finalCodeLines.splice(insertIndex + 1, 0, modalHTML);
  } else {
      console.log('Could not find injection point for modal. Appending to end of template.');
      const templateEnd = finalCodeLines.findIndex(line => line === '</template>');
      if (templateEnd !== -1) {
          finalCodeLines.splice(templateEnd, 0, modalHTML);
      }
  }

  const finalCode = finalCodeLines.join('\n');
  fs.writeFileSync('c:/SportGo/resources/js/views/owner/OwnerVenuePosts.vue', finalCode);
  console.log('Successfully restored OwnerVenuePosts.vue with delete modal');
} else {
  console.log('Could not find the view_file responses');
}
