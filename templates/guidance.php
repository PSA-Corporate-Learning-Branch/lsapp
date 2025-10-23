<div class="p-3 rounded-3 bg-dark-subtle">
    <div class="mb-2">
        <a target="_blank"
            rel="nooperner"
            href="https://bcgov.sharepoint.com/:w:/r/teams/00440/Shared%20Documents/LC%20design_faciliation%20guidelines/CL-course-revision-process.docx?d=wda0cc1137afc430eabc7c800b986e62a&csf=1&web=1&e=vqHp7k&xsdata=MDV8MDJ8QWxsYW4uSGFnZ2V0dEBnb3YuYmMuY2F8NjA1NGMzYTk2Y2U5NGI5MDgzYTYwOGRkN2JhMGFiODZ8NmZkYjUyMDAzZDBkNGE4YWIwMzZkMzY4NWUzNTlhZGN8MHwwfDYzODgwMjY1MjE2NzgxODM0MHxVbmtub3dufFRXRnBiR1pzYjNkOGV5SkZiWEIwZVUxaGNHa2lPblJ5ZFdVc0lsWWlPaUl3TGpBdU1EQXdNQ0lzSWxBaU9pSlhhVzR6TWlJc0lrRk9Jam9pVFdGcGJDSXNJbGRVSWpveWZRPT18MHx8fA%3d%3d&sdata=WWxaSW90YjRRazZmaVM2NStDZDcyQTlSNGZRWnYvNzZjZkhHREcrK2h4QT0%3d" 
            class="btn btn-secondary">
                Process documentation
        </a>
    </div>
    <?php $thisdoer = ''; if($formData['assign_to'] == LOGGED_IN_IDIR) $thisdoer = 'open'; ?>
    <details class="mb-2" <?= $thisdoer ?>>
        <summary class="mb-2"><?= $cat ?> guidance</summary>
        <div class="p-2 rounded-3 bg-light-subtle">
        <?= $Parsedown->text($guidance) ?>
        </div>
    </details>
    <details id="scopeguide">
    <summary class="mb-2">Scope guidance</summary>
        <div class="mb-2 p-2 bg-light-subtle rounded-2">
            <h3>Minor Change </h3>
            <div><strong>1/2 day or less </strong></div>
            <p>Small revisions to existing content that don't significantly change the 
                meaning/consultation with the business owner is not required e.g., typos, updating 
                links to existing or new versions of small assets (e.g., images), minor bug fixes 
                that don't significantly alter the user experience, changes that don't require 
                extensive testing, small adjustments to quiz questions in Moodle or HTML.</p>
        </div>
        <div class="mb-2 p-2 bg-light-subtle rounded-2">
            <h3>Moderate Change </h3>
            <div><strong>More than &#189; day - 1 day </strong></div>
            <p>Moderate changes to content (needing business owner approval), updating or 
                reorganizing content in multiple lessons or modules, adding/updating evaluation 
                surveys, adjustments to quizzes built in Storyline, updating videos/interactive 
                activities, adding new activities/quizzes, multiple changes from an annual review, 
                or changes that require more than one person (e.g., a developer).</p>
        </div>
        <div class="mb-2 p-2 bg-light-subtle rounded-2">
            <h3>Major Change </h3>
            <div><strong>More than one day </strong></div>
            <p>Course overhauls or complete reorganization of existing content, revising learning 
                objectives, creating videos, simulations, requires extensive consultation with 
                business owners.</p>
        </div>   
    
    </details>
    <details id="approvalguide">
    <summary class="mb-2">Approval guidance</summary>
    <dl>
        <dt>Pending Approval</dt>
        <dd>Work has been completed or proposed and is awaiting approval from leadership or partner before proceeding. </dd>

        <dt>Denied</dt>
        <dd>Proposed work has been reviewed but not approved. </dd>

        <dt>On Hold</dt>
        <dd>Work has been temporarily paused. No further action is being taken until the issue is resolved or new instructions are provided. </dd>

        <dt>Approved</dt>
        <dd>Work has been reviewed and approved. It is ready to move forward with implementation or publication. </dd>
    </dl>
    </details>
    <details id="progressguide">
    <summary class="mb-2">Progress guidance</summary>
    <dl>
        <dt>Not started</dt>
        <dd>Work has not yet begun. Tasks or changes are planned, but not yet initiated.  </dd>

        <dt>In progress</dt>
        <dd>Work is currently taking place but not yet complete.  </dd>

        <dt>In review</dt>
        <dd>Work has been completed and is under review. It may be examined for quality, accuracy, or approval before moving forward. </dd>

        <dt>Ready to publish</dt>
        <dd>Work has passed review and is finalized. It is now prepared for publication or release to the audience. </dd>

        <dt>Closed = work has been fully implemented and finalized, and no further action is required. The course change process is considered complete. 
    </dl>
    </details>

</div>