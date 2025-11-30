<style>
  /* Move AI FAB ABOVE the WhatsApp bubble and on top of everything */
  .ea-ai-fab{
    position:fixed;
    right:20px;          /* same right edge as WhatsApp */
    bottom:96px;         /* ABOVE WhatsApp (which is ~20px from bottom) */
    z-index:2147483000;  /* higher than most widgets */
    display:block;
  }
  .ea-ai-fab button{
    width:56px;height:56px;border-radius:50%;border:none;
    box-shadow:0 4px 12px rgba(0,0,0,.2);
    background:#1f2937;color:#fff;font-weight:600;cursor:pointer
  }
  .ea-ai-panel{
    position:fixed;
    right:20px;
    bottom:160px;        /* opens above the FAB */
    width:360px; max-width:90vw;
    background:#fff; border:1px solid #e5e7eb; border-radius:12px;
    box-shadow:0 10px 24px rgba(0,0,0,.18);
    overflow:hidden; display:none;
    z-index:2147483000;  /* ensure panel sits on top too */
  }
  .ea-ai-head{ background:#111827;color:#fff;padding:10px 12px;font-weight:600;font-size:14px }
  .ea-ai-body{ padding:10px }
  .ea-ai-log{ height:240px;overflow:auto;font-size:14px;background:#f9fafb;padding:8px;border-radius:8px }
  .ea-ai-row{ display:flex;gap:6px;margin-top:8px }
  .ea-ai-row input{ flex:1;padding:8px;border:1px solid #e5e7eb;border-radius:8px }
  .ea-ai-row button{ padding:8px 12px;border:none;background:#111827;color:#fff;border-radius:8px;cursor:pointer }
  @media (max-width:600px){
    .ea-ai-fab{ bottom:88px }     /* keep it above WhatsApp on mobile */
    .ea-ai-panel{ right:10px; width:94vw; bottom:150px }
  }
</style>
