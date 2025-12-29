# AI Instructions: Generate Plugin Architecture and Development Plan from PRD

## Context
You are a senior WordPress plugin architect tasked with creating comprehensive technical documentation and an implementation plan from a Product Requirements Document (PRD). You must follow the Quadica development standards defined in CLAUDE.md and in other documents referenced by CLAUDE.md.

## Phase 1: Architecture Design (Create 4 Documents)

**Objective:** Design the complete technical architecture before writing any implementation plan.

**Instructions:**

### 1. Read and Analyze the PRD
- Identify all functional requirements
- Extract technical constraints and dependencies
- Note performance requirements
- Understand data flow and user interactions

### 2. Create PLUGIN-ARCHITECTURE.md
- Document the overall architectural approach
- Define core design patterns (MVC, dependency injection, etc.)
- Specify architectural layers (data, business logic, presentation)
- List all major components and their responsibilities
- Document integration points (WooCommerce hooks, Action Scheduler, carrier APIs, AI services)
- Include security architecture (authentication, authorization, data encryption)
- Define error handling strategy
- Specify caching strategy
- Document any architectural constraints from CLAUDE.md (e.g., no AJAX unless justified, manual SQL deployment)

### 3. Create FILE-STRUCTURE.md
- Map out the complete directory structure
- List every file that will be created (with full path)
- Organize by component/feature area
- Include purpose/description for each file
- Show which files depend on which (import relationships)
- Follow WordPress plugin conventions
- Ensure structure matches Quadica standards from CLAUDE.md

### 4. Create CLASS-DIAGRAM.md
- Document every class that will be implemented
- Show class hierarchies (inheritance, interfaces, abstract classes)
- Define all public methods and properties for each class
- Show relationships between classes (composition, aggregation, dependencies)
- Use Mermaid diagram syntax or clear text representation
- Include utility classes, controllers, adapters, and models
- Document which design patterns each class implements

### 5. Create DATA-FLOW.md
- Map out all data flows through the system
- Document each major workflow from start to finish:
- Show decision points and conditional paths
- Include error handling flows
- Document API request/response flows
- Show background job flows (Action Scheduler tasks)
- Indicate where data is transformed or validated

## Phase 2: Development Plan (Create DEVELOPMENT-PLAN.md)

**Objective:** Break down the architecture into a phased implementation plan.

**Instructions:**

### 1. Analyze Dependencies
- Review the 4 architecture documents
- Identify which components depend on others
- Determine the logical build order (foundation → utilities → core → features → UI)

### 2. Create DEVELOPMENT-PLAN.md
- Break implementation into 20-30 focused phases
- Each phase should have:
  - **Goal:** Clear objective statement
  - **Duration:** Realistic time estimate (0.5 - 2 days)
  - **Dependencies:** Which phases must complete first
  - **Tasks:** 2-4 specific, actionable tasks
  - **Completion Criteria:** Measurable success indicators

### 3. Phase Organization Strategy
- Start with database foundation (no code dependencies)
- Build infrastructure next (autoloader, core classes, utilities)
- Implement data layer before business logic
- Add integrations before orchestration
- Build backend features before admin UI
- Add styling and JavaScript last
- End with testing and documentation phases

### 4. Include Supporting Sections
- Development workflow (commit, push, test, deploy cycle)
- Progress tracking guidelines
- Overall success criteria (functional, performance, technical)
- Estimated timeline with milestones
- Testing strategy for each phase

## Key Principles

- **Architecture First:** Never start the development plan until all 4 architecture documents are complete and reviewed
- **Dependency Order:** Ensure phases build on each other logically
- **Testability:** Each phase should be independently testable
- **Small Increments:** Keep phases small (2-4 tasks) to enable frequent validation
- **Standards Compliance:** All design decisions must align with CLAUDE.md, SECURITY.md, and AJAX.md
- **No Assumptions:** Verify everything against official WordPress/WooCommerce documentation

## Output Format

Deliver 5 markdown documents:
1. `docs/PLUGIN-ARCHITECTURE.md`
2. `docs/FILE-STRUCTURE.md`
3. `docs/CLASS-DIAGRAM.md`
4. `docs/DATA-FLOW.md`
5. `DEVELOPMENT-PLAN.md`

## Review Criteria

Before finalizing, verify:
- [ ] All PRD requirements mapped to architecture
- [ ] All architectural decisions justify their approach
- [ ] File structure follows WordPress conventions
- [ ] All classes have clear single responsibilities
- [ ] Data flows handle all edge cases and errors
- [ ] Development plan covers 100% of architecture
- [ ] Phases have clear dependencies and success criteria
- [ ] Timeline is realistic (typically 6-10 weeks for major plugins)
- [ ] Security, performance, and scalability addressed
- [ ] Quadica standards (CLAUDE.md, SECURITY.md, AJAX.md) followed throughout

---
