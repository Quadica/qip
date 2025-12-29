import React, { useState } from 'react';
import { ChevronLeft, ChevronRight, Play, CheckCircle, Loader2, FileCode, Layers, Grid3X3, Clock, RefreshCw } from 'lucide-react';

/*
 * Luxeon Star LEDs / Quadica LEDs Brand Guidelines - DARK THEME
 * Engraving Queue Screen - displayed after clicking "Start Engraving" on Batch Creator
 */

// Brand color constants for dark theme
const colors = {
  // Backgrounds
  bgPrimary: '#0a1628',
  bgCard: '#0f1f35',
  bgElevated: '#152a45',
  bgHover: '#1a3352',
  
  // Brand blues
  deepNavy: '#01236d',
  luxeonBlue: '#0056A4',
  electricBlue: '#00A0E3',
  skyBlue: '#109cf6',
  coolLed: '#87CEEB',
  
  // Text
  textPrimary: '#FFFFFF',
  textSecondary: '#A8B4BD',
  textMuted: '#6b7c8f',
  
  // Accents
  warmLed: '#FFB347',
  success: '#28A745',
  alert: '#DC3545',
  
  // Borders
  border: '#1e3a5f',
  borderLight: '#2a4a6f',
};

// Mock data for the engraving queue based on the batch created
// This simulates what would be generated from the Module Engraving Batch Creator
const mockQueueData = [
  {
    id: 1,
    groupType: 'Same ID × Full',
    moduleType: 'CORE',
    modules: [
      { sku: 'CORE-91247', qty: 26 }
    ],
    totalModules: 26,
    arrayCount: 4,
    status: 'pending' // pending, in_progress, complete
  },
  {
    id: 2,
    groupType: 'Same ID × Partial',
    moduleType: 'CORE',
    modules: [
      { sku: 'CORE-63958', qty: 5 }
    ],
    totalModules: 5,
    arrayCount: 1,
    status: 'pending'
  },
  {
    id: 3,
    groupType: 'Mixed ID × Full',
    moduleType: 'SOLO',
    modules: [
      { sku: 'SOLO-34543', qty: 3 },
      { sku: 'SOLO-78291', qty: 2 },
      { sku: 'SOLO-45182', qty: 3 }
    ],
    totalModules: 8,
    arrayCount: 1,
    status: 'pending'
  },
  {
    id: 4,
    groupType: 'Mixed ID × Partial',
    moduleType: 'EDGE',
    modules: [
      { sku: 'EDGE-58324', qty: 2 },
      { sku: 'EDGE-19847', qty: 3 }
    ],
    totalModules: 5,
    arrayCount: 1,
    status: 'pending'
  },
  {
    id: 5,
    groupType: 'Same ID × Full',
    moduleType: 'STAR',
    modules: [
      { sku: 'STAR-84219', qty: 24 }
    ],
    totalModules: 24,
    arrayCount: 3,
    status: 'pending'
  }
];

export default function EngravingQueue() {
  const [queueItems, setQueueItems] = useState(mockQueueData);
  const [activeItemId, setActiveItemId] = useState(null);
  const [startOffsets, setStartOffsets] = useState({});
  const [currentSvgStep, setCurrentSvgStep] = useState({}); // Track which SVG step we're on for each row

  // Calculate SVG breakdown for a row based on total modules and start offset
  const calculateSvgBreakdown = (totalModules, startOffset) => {
    const svgSteps = [];
    let remainingModules = totalModules;
    let currentPosition = startOffset;
    
    // First SVG - may be partial if starting offset > 1
    if (startOffset > 1) {
      const firstSvgModules = Math.min(remainingModules, 9 - startOffset); // positions startOffset to 8
      svgSteps.push({
        step: 1,
        startPos: startOffset,
        endPos: startOffset + firstSvgModules - 1,
        moduleCount: firstSvgModules,
        arrayCount: 1,
        isPartial: true,
        description: `Positions ${startOffset}-${startOffset + firstSvgModules - 1}`
      });
      remainingModules -= firstSvgModules;
      currentPosition = 1;
    }
    
    // Middle SVGs - full arrays (8 modules each), can be reused
    if (remainingModules >= 8) {
      const fullArrayCount = Math.floor(remainingModules / 8);
      svgSteps.push({
        step: svgSteps.length + 1,
        startPos: 1,
        endPos: 8,
        moduleCount: 8,
        arrayCount: fullArrayCount,
        isPartial: false,
        description: 'Positions 1-8'
      });
      remainingModules -= fullArrayCount * 8;
    }
    
    // Final SVG - partial array if there are remaining modules
    if (remainingModules > 0) {
      svgSteps.push({
        step: svgSteps.length + 1,
        startPos: 1,
        endPos: remainingModules,
        moduleCount: remainingModules,
        arrayCount: 1,
        isPartial: true,
        description: `Positions 1-${remainingModules}`
      });
    }
    
    return svgSteps;
  };

  // Get current SVG step for an item (defaults to 0 = not started)
  const getCurrentSvgStep = (itemId) => {
    return currentSvgStep[itemId] || 0;
  };

  // Calculate total arrays needed based on modules and start offset
  const calculateTotalArrays = (totalModules, startOffset) => {
    let remainingModules = totalModules;
    let arrayCount = 0;
    
    // First array - may be partial if starting offset > 1
    if (startOffset > 1) {
      const firstArrayModules = Math.min(remainingModules, 9 - startOffset);
      arrayCount += 1;
      remainingModules -= firstArrayModules;
    }
    
    // Full arrays
    if (remainingModules > 0) {
      arrayCount += Math.ceil(remainingModules / 8);
    }
    
    return arrayCount;
  };

  // Start engraving for an item (begins first SVG step)
  const startEngraving = (itemId) => {
    const item = queueItems.find(i => i.id === itemId);
    const offset = getStartOffset(itemId);
    const svgSteps = calculateSvgBreakdown(item.totalModules, offset);
    
    setActiveItemId(itemId);
    setCurrentSvgStep(prev => ({ ...prev, [itemId]: 1 }));
    setQueueItems(prev => prev.map(item => 
      item.id === itemId ? { ...item, status: 'in_progress' } : item
    ));
    
    // TODO: Generate first SVG and send to LightBurn
    console.log('Generate SVG step 1 of', svgSteps.length, 'for item:', itemId);
    console.log('SVG details:', svgSteps[0]);
  };

  // Advance to next SVG step
  const nextSvgStep = (itemId) => {
    const item = queueItems.find(i => i.id === itemId);
    const offset = getStartOffset(itemId);
    const svgSteps = calculateSvgBreakdown(item.totalModules, offset);
    const currentStep = getCurrentSvgStep(itemId);
    const nextStep = currentStep + 1;
    
    setCurrentSvgStep(prev => ({ ...prev, [itemId]: nextStep }));
    
    // TODO: Generate next SVG and send to LightBurn
    console.log('Generate SVG step', nextStep, 'of', svgSteps.length, 'for item:', itemId);
    console.log('SVG details:', svgSteps[nextStep - 1]);
  };

  // Go back to previous SVG step
  const prevSvgStep = (itemId) => {
    const item = queueItems.find(i => i.id === itemId);
    const offset = getStartOffset(itemId);
    const svgSteps = calculateSvgBreakdown(item.totalModules, offset);
    const currentStep = getCurrentSvgStep(itemId);
    const prevStep = Math.max(1, currentStep - 1);
    
    setCurrentSvgStep(prev => ({ ...prev, [itemId]: prevStep }));
    
    // TODO: Generate previous SVG and send to LightBurn
    console.log('Go back to SVG step', prevStep, 'of', svgSteps.length, 'for item:', itemId);
    console.log('SVG details:', svgSteps[prevStep - 1]);
  };

  // Resend current SVG
  const resendCurrentSvg = (itemId) => {
    const item = queueItems.find(i => i.id === itemId);
    const offset = getStartOffset(itemId);
    const svgSteps = calculateSvgBreakdown(item.totalModules, offset);
    const currentStep = getCurrentSvgStep(itemId);
    
    // TODO: Resend current SVG to LightBurn
    console.log('Resend SVG step', currentStep, 'of', svgSteps.length, 'for item:', itemId);
    console.log('SVG details:', svgSteps[currentStep - 1]);
  };

  // Rerun completed row - reset to pending so operator can adjust start position if needed
  const rerunEngraving = (itemId) => {
    setActiveItemId(null);
    setCurrentSvgStep(prev => ({ ...prev, [itemId]: 0 }));
    setQueueItems(prev => prev.map(item => 
      item.id === itemId ? { ...item, status: 'pending' } : item
    ));
    
    console.log('Rerun requested for item:', itemId, '- reset to pending, operator can adjust start position');
  };

  // Get start offset for an item (defaults to 1)
  const getStartOffset = (itemId) => {
    return startOffsets[itemId] !== undefined ? startOffsets[itemId] : 1;
  };

  // Update start offset for an item
  const updateStartOffset = (itemId, value) => {
    const numValue = parseInt(value) || 1;
    const clampedValue = Math.max(1, Math.min(8, numValue));
    setStartOffsets(prev => ({
      ...prev,
      [itemId]: clampedValue
    }));
  };

  // Complete engraving for an item (all SVG steps done)
  const completeEngraving = (itemId) => {
    setQueueItems(prev => prev.map(item => 
      item.id === itemId ? { ...item, status: 'complete' } : item
    ));
    setActiveItemId(null);
    setCurrentSvgStep(prev => ({ ...prev, [itemId]: 0 }));
    // TODO: Update database records
    console.log('Mark engraving complete for item:', itemId);
  };

  // Calculate totals
  const totalArrays = queueItems.reduce((sum, item) => 
    sum + calculateTotalArrays(item.totalModules, getStartOffset(item.id)), 0);
  const completedArrays = queueItems
    .filter(item => item.status === 'complete')
    .reduce((sum, item) => sum + calculateTotalArrays(item.totalModules, getStartOffset(item.id)), 0);
  const totalModules = queueItems.reduce((sum, item) => sum + item.totalModules, 0);
  const completedModules = queueItems
    .filter(item => item.status === 'complete')
    .reduce((sum, item) => sum + item.totalModules, 0);

  // Get status badge styling
  const getStatusStyle = (status) => {
    switch (status) {
      case 'complete':
        return { bg: `${colors.success}20`, color: colors.success, text: 'Complete' };
      case 'in_progress':
        return { bg: `${colors.warmLed}20`, color: colors.warmLed, text: 'In Progress' };
      default:
        return { bg: colors.bgElevated, color: colors.textMuted, text: 'Pending' };
    }
  };

  // Get group type badge color
  const getGroupTypeColor = (groupType) => {
    if (groupType.includes('Same ID') && groupType.includes('Full')) return colors.electricBlue;
    if (groupType.includes('Same ID') && groupType.includes('Partial')) return colors.skyBlue;
    if (groupType.includes('Mixed ID') && groupType.includes('Full')) return colors.warmLed;
    return colors.coolLed;
  };

  return (
    <div 
      className="min-h-screen p-6"
      style={{ 
        fontFamily: "'Roboto', 'Segoe UI', 'Arial', sans-serif",
        backgroundColor: colors.bgPrimary,
        color: colors.textPrimary
      }}
    >
      <div className="max-w-5xl mx-auto">
        {/* Brand Header Bar */}
        <div 
          className="rounded-t-lg px-6 py-4 flex items-center justify-between"
          style={{ 
            background: `linear-gradient(135deg, ${colors.deepNavy} 0%, ${colors.luxeonBlue} 100%)`,
            borderBottom: `1px solid ${colors.electricBlue}`
          }}
        >
          <div className="flex items-center gap-3">
            <button
              onClick={() => {
                // TODO: Navigate back to Batch Creator
                console.log('Navigate back to Batch Creator');
              }}
              className="p-2 rounded transition-all"
              style={{ color: colors.coolLed }}
              onMouseOver={(e) => {
                e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.1)';
              }}
              onMouseOut={(e) => {
                e.currentTarget.style.backgroundColor = 'transparent';
              }}
              title="Back to Batch Creator"
            >
              <ChevronLeft className="w-6 h-6" />
            </button>
            <div 
              className="w-10 h-10 rounded-lg flex items-center justify-center"
              style={{ 
                backgroundColor: colors.electricBlue,
                boxShadow: `0 0 20px ${colors.electricBlue}40`
              }}
            >
              <Grid3X3 className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-white tracking-tight">
                Engraving Queue
              </h1>
              <p className="text-sm" style={{ color: colors.skyBlue }}>
                LUXEON STAR LEDs Production System
              </p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-xs" style={{ color: colors.coolLed }}>Quadica Developments Inc.</p>
          </div>
        </div>

        {/* Progress Stats Bar */}
        <div 
          className="grid grid-cols-4"
          style={{ 
            backgroundColor: colors.bgCard,
            borderLeft: `1px solid ${colors.border}`,
            borderRight: `1px solid ${colors.border}`
          }}
        >
          <div 
            className="p-4 text-center"
            style={{ borderRight: `1px solid ${colors.border}` }}
          >
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Queue Items</div>
            <div className="text-2xl font-bold" style={{ color: colors.skyBlue }}>{queueItems.length}</div>
          </div>
          <div 
            className="p-4 text-center"
            style={{ borderRight: `1px solid ${colors.border}` }}
          >
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Arrays</div>
            <div className="text-2xl font-bold">
              <span style={{ color: colors.success }}>{completedArrays}</span>
              <span style={{ color: colors.textMuted }}> / {totalArrays}</span>
            </div>
          </div>
          <div 
            className="p-4 text-center"
            style={{ borderRight: `1px solid ${colors.border}` }}
          >
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Modules</div>
            <div className="text-2xl font-bold">
              <span style={{ color: colors.success }}>{completedModules}</span>
              <span style={{ color: colors.textMuted }}> / {totalModules}</span>
            </div>
          </div>
          <div className="p-4 text-center">
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Progress</div>
            <div className="text-2xl font-bold" style={{ color: colors.electricBlue }}>
              {totalArrays > 0 ? Math.round((completedArrays / totalArrays) * 100) : 0}%
            </div>
          </div>
        </div>

        {/* Progress Bar */}
        <div 
          style={{ 
            backgroundColor: colors.bgElevated,
            borderLeft: `1px solid ${colors.border}`,
            borderRight: `1px solid ${colors.border}`,
            padding: '0 16px 16px 16px'
          }}
        >
          <div 
            className="h-2 rounded-full overflow-hidden"
            style={{ backgroundColor: colors.bgPrimary }}
          >
            <div 
              className="h-full rounded-full transition-all duration-500"
              style={{ 
                width: `${totalArrays > 0 ? (completedArrays / totalArrays) * 100 : 0}%`,
                backgroundColor: colors.success,
                boxShadow: `0 0 10px ${colors.success}50`
              }}
            />
          </div>
        </div>

        {/* Queue List */}
        <div 
          className="rounded-b-lg overflow-hidden"
          style={{ border: `1px solid ${colors.border}` }}
        >
          {/* Header */}
          <div 
            className="px-4 flex items-center gap-2"
            style={{ 
              backgroundColor: colors.bgElevated,
              borderBottom: `1px solid ${colors.border}`,
              height: '48px'
            }}
          >
            <Layers className="w-4 h-4" style={{ color: colors.electricBlue }} />
            <span 
              className="text-xs uppercase tracking-wider font-semibold"
              style={{ color: colors.electricBlue }}
            >
              Array Engraving Queue
            </span>
          </div>

          {/* Queue Items */}
          <div style={{ backgroundColor: colors.bgCard }}>
            {queueItems.map((item, index) => {
              const statusStyle = getStatusStyle(item.status);
              const groupTypeColor = getGroupTypeColor(item.groupType);
              const isLast = index === queueItems.length - 1;
              
              return (
                <div 
                  key={item.id}
                  className="p-4"
                  style={{ 
                    borderBottom: isLast ? 'none' : `1px solid ${colors.border}`,
                    backgroundColor: item.status === 'in_progress' ? `${colors.warmLed}08` : 'transparent'
                  }}
                >
                  {/* Row Header */}
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-3">
                      {/* Status Icon */}
                      <div 
                        className="w-8 h-8 rounded-full flex items-center justify-center"
                        style={{ 
                          backgroundColor: statusStyle.bg,
                          boxShadow: item.status !== 'pending' ? `0 0 10px ${statusStyle.color}30` : 'none'
                        }}
                      >
                        {item.status === 'complete' && <CheckCircle className="w-4 h-4" style={{ color: statusStyle.color }} />}
                        {item.status === 'in_progress' && <Loader2 className="w-4 h-4 animate-spin" style={{ color: statusStyle.color }} />}
                        {item.status === 'pending' && <Clock className="w-4 h-4" style={{ color: statusStyle.color }} />}
                      </div>
                      
                      {/* Module Type (Array Name) - Most important for operator */}
                      <span 
                        className="px-3 py-1 rounded text-sm font-bold"
                        style={{ 
                          fontFamily: "'Roboto Mono', 'Consolas', monospace",
                          backgroundColor: `${colors.electricBlue}25`,
                          color: colors.skyBlue,
                          border: `1px solid ${colors.electricBlue}50`
                        }}
                      >
                        {item.moduleType}
                      </span>
                      
                      {/* Group Type Badge */}
                      <span 
                        className="px-2 py-1 rounded text-xs"
                        style={{ 
                          backgroundColor: `${groupTypeColor}20`,
                          color: groupTypeColor
                        }}
                      >
                        {item.groupType}
                      </span>
                      
                      {/* Status Badge */}
                      <span 
                        className="px-2 py-1 rounded text-xs"
                        style={{ 
                          backgroundColor: statusStyle.bg,
                          color: statusStyle.color
                        }}
                      >
                        {statusStyle.text}
                      </span>
                    </div>
                    
                    {/* Action Buttons */}
                    <div className="flex items-center gap-2">
                      {item.status === 'pending' && (
                        <button
                          onClick={() => startEngraving(item.id)}
                          className="px-4 py-2 rounded font-bold text-sm flex items-center gap-2 transition-all"
                          style={{ 
                            backgroundColor: colors.electricBlue,
                            color: '#FFFFFF',
                            boxShadow: `0 0 15px ${colors.electricBlue}30`
                          }}
                          onMouseOver={(e) => {
                            e.currentTarget.style.backgroundColor = colors.skyBlue;
                            e.currentTarget.style.boxShadow = `0 0 25px ${colors.skyBlue}50`;
                          }}
                          onMouseOut={(e) => {
                            e.currentTarget.style.backgroundColor = colors.electricBlue;
                            e.currentTarget.style.boxShadow = `0 0 15px ${colors.electricBlue}30`;
                          }}
                        >
                          <Play className="w-4 h-4" />
                          Engrave
                        </button>
                      )}
                      {item.status === 'in_progress' && (() => {
                        const svgSteps = calculateSvgBreakdown(item.totalModules, getStartOffset(item.id));
                        const currentStep = getCurrentSvgStep(item.id);
                        const isLastStep = currentStep >= svgSteps.length;
                        const isFirstStep = currentStep <= 1;
                        
                        return (
                          <div className="flex items-center gap-2">
                            {/* Back Button - only show if not on first step */}
                            {!isFirstStep && (
                              <button
                                onClick={() => prevSvgStep(item.id)}
                                className="px-3 py-2 rounded font-medium text-sm flex items-center gap-1 transition-all"
                                style={{ 
                                  backgroundColor: colors.bgElevated,
                                  color: colors.textSecondary,
                                  border: `1px solid ${colors.border}`
                                }}
                                onMouseOver={(e) => {
                                  e.currentTarget.style.backgroundColor = colors.bgHover;
                                  e.currentTarget.style.borderColor = colors.borderLight;
                                }}
                                onMouseOut={(e) => {
                                  e.currentTarget.style.backgroundColor = colors.bgElevated;
                                  e.currentTarget.style.borderColor = colors.border;
                                }}
                                title="Go back to previous SVG"
                              >
                                <ChevronLeft className="w-4 h-4" />
                                Back
                              </button>
                            )}
                            
                            {/* Resend Button */}
                            <button
                              onClick={() => resendCurrentSvg(item.id)}
                              className="px-3 py-2 rounded font-medium text-sm flex items-center gap-1 transition-all"
                              style={{ 
                                backgroundColor: colors.bgElevated,
                                color: colors.skyBlue,
                                border: `1px solid ${colors.skyBlue}50`
                              }}
                              onMouseOver={(e) => {
                                e.currentTarget.style.backgroundColor = `${colors.skyBlue}20`;
                                e.currentTarget.style.borderColor = colors.skyBlue;
                              }}
                              onMouseOut={(e) => {
                                e.currentTarget.style.backgroundColor = colors.bgElevated;
                                e.currentTarget.style.borderColor = `${colors.skyBlue}50`;
                              }}
                              title="Resend current SVG to laser"
                            >
                              <RefreshCw className="w-4 h-4" />
                              Resend
                            </button>
                            
                            {/* Next or Complete Button */}
                            {isLastStep ? (
                              <button
                                onClick={() => completeEngraving(item.id)}
                                className="px-4 py-2 rounded font-bold text-sm flex items-center gap-2 transition-all"
                                style={{ 
                                  backgroundColor: colors.success,
                                  color: '#FFFFFF',
                                  boxShadow: `0 0 15px ${colors.success}30`
                                }}
                                onMouseOver={(e) => {
                                  e.currentTarget.style.boxShadow = `0 0 25px ${colors.success}50`;
                                }}
                                onMouseOut={(e) => {
                                  e.currentTarget.style.boxShadow = `0 0 15px ${colors.success}30`;
                                }}
                              >
                                <CheckCircle className="w-4 h-4" />
                                Complete
                              </button>
                            ) : (
                              <button
                                onClick={() => nextSvgStep(item.id)}
                                className="px-4 py-2 rounded font-bold text-sm flex items-center gap-2 transition-all"
                                style={{ 
                                  backgroundColor: colors.warmLed,
                                  color: '#000000',
                                  boxShadow: `0 0 15px ${colors.warmLed}30`
                                }}
                                onMouseOver={(e) => {
                                  e.currentTarget.style.boxShadow = `0 0 25px ${colors.warmLed}50`;
                                }}
                                onMouseOut={(e) => {
                                  e.currentTarget.style.boxShadow = `0 0 15px ${colors.warmLed}30`;
                                }}
                              >
                                <ChevronRight className="w-4 h-4" />
                                Next SVG
                              </button>
                            )}
                          </div>
                        );
                      })()}
                      {item.status === 'complete' && (
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => rerunEngraving(item.id)}
                            className="px-3 py-2 rounded font-medium text-sm flex items-center gap-1 transition-all"
                            style={{ 
                              backgroundColor: colors.bgElevated,
                              color: colors.textSecondary,
                              border: `1px solid ${colors.border}`
                            }}
                            onMouseOver={(e) => {
                              e.currentTarget.style.backgroundColor = colors.bgHover;
                              e.currentTarget.style.borderColor = colors.borderLight;
                            }}
                            onMouseOut={(e) => {
                              e.currentTarget.style.backgroundColor = colors.bgElevated;
                              e.currentTarget.style.borderColor = colors.border;
                            }}
                            title="Rerun engraving from beginning"
                          >
                            <RefreshCw className="w-4 h-4" />
                            Rerun
                          </button>
                          <span 
                            className="px-4 py-2 rounded text-sm flex items-center gap-2"
                            style={{ 
                              backgroundColor: `${colors.success}20`,
                              color: colors.success
                            }}
                          >
                            <CheckCircle className="w-4 h-4" />
                            Done
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                  
                  {/* Row Details */}
                  <div 
                    className="flex items-center gap-6 ml-11 text-sm"
                    style={{ color: colors.textSecondary }}
                  >
                    {/* Modules List */}
                    <div className="flex items-center gap-2">
                      <FileCode className="w-4 h-4" style={{ color: colors.textMuted }} />
                      <span style={{ color: colors.textMuted }}>Modules:</span>
                      <div className="flex items-center gap-2">
                        {item.modules.map((mod, i) => (
                          <span 
                            key={i}
                            className="px-2 py-0.5 rounded text-xs"
                            style={{ 
                              fontFamily: "'Roboto Mono', 'Consolas', monospace",
                              backgroundColor: colors.bgElevated,
                              color: colors.textSecondary
                            }}
                          >
                            {mod.sku} ×{mod.qty}
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                  
                  {/* Stats Row */}
                  <div 
                    className="flex items-center gap-6 ml-11 mt-2 text-sm"
                  >
                    <div className="flex items-center gap-2">
                      <span style={{ color: colors.textMuted }}>Arrays:</span>
                      <span className="font-bold" style={{ color: colors.textPrimary }}>
                        {calculateTotalArrays(item.totalModules, getStartOffset(item.id))}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span style={{ color: colors.textMuted }}>Total Modules:</span>
                      <span className="font-bold" style={{ color: colors.textPrimary }}>{item.totalModules}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span style={{ color: colors.textMuted }}>Start Position:</span>
                      <input
                        type="number"
                        min="1"
                        max="8"
                        value={getStartOffset(item.id)}
                        onChange={(e) => updateStartOffset(item.id, e.target.value)}
                        disabled={item.status !== 'pending'}
                        className="w-12 px-2 py-0.5 rounded text-center text-sm font-bold"
                        style={{
                          backgroundColor: item.status === 'pending' ? colors.bgElevated : colors.bgPrimary,
                          color: item.status === 'pending' ? colors.textPrimary : colors.textMuted,
                          border: `1px solid ${item.status === 'pending' ? colors.border : colors.bgElevated}`,
                          outline: 'none',
                          opacity: item.status === 'pending' ? 1 : 0.6
                        }}
                        onFocus={(e) => {
                          if (item.status === 'pending') {
                            e.target.style.borderColor = colors.electricBlue;
                            e.target.style.boxShadow = `0 0 0 2px ${colors.electricBlue}30`;
                          }
                        }}
                        onBlur={(e) => {
                          e.target.style.borderColor = colors.border;
                          e.target.style.boxShadow = 'none';
                        }}
                        title="Starting position on array (1-8)"
                      />
                    </div>
                  </div>
                  
                  {/* Current SVG Step Details - shown when in progress */}
                  {item.status === 'in_progress' && (() => {
                    const svgSteps = calculateSvgBreakdown(item.totalModules, getStartOffset(item.id));
                    const currentStep = getCurrentSvgStep(item.id);
                    const currentSvgDetails = svgSteps[currentStep - 1];
                    
                    if (!currentSvgDetails) return null;
                    
                    return (
                      <div 
                        className="ml-11 mt-3 p-3 rounded-lg"
                        style={{ 
                          backgroundColor: `${colors.warmLed}15`,
                          border: `1px solid ${colors.warmLed}30`
                        }}
                      >
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-4">
                            {/* SVG Step Indicator */}
                            <div 
                              className="flex items-center gap-2 px-3 py-1 rounded-full"
                              style={{ 
                                backgroundColor: colors.warmLed,
                                color: '#000000'
                              }}
                            >
                              <FileCode className="w-4 h-4" />
                              <span className="font-bold text-sm">
                                SVG {currentStep} of {svgSteps.length}
                              </span>
                            </div>
                            
                            {/* Positions */}
                            <div className="flex items-center gap-2">
                              <span style={{ color: colors.textMuted }}>Positions:</span>
                              <span 
                                className="font-bold px-2 py-0.5 rounded"
                                style={{ 
                                  backgroundColor: colors.bgElevated,
                                  color: colors.textPrimary
                                }}
                              >
                                {currentSvgDetails.startPos} - {currentSvgDetails.endPos}
                              </span>
                            </div>
                            
                            {/* Module Count */}
                            <div className="flex items-center gap-2">
                              <span style={{ color: colors.textMuted }}>Modules:</span>
                              <span className="font-bold" style={{ color: colors.textPrimary }}>
                                {currentSvgDetails.moduleCount}
                              </span>
                            </div>
                            
                            {/* Array Count */}
                            <div 
                              className="flex items-center gap-2 px-3 py-1 rounded"
                              style={{ 
                                backgroundColor: colors.electricBlue,
                                color: '#FFFFFF'
                              }}
                            >
                              <Grid3X3 className="w-4 h-4" />
                              <span className="font-bold">
                                {currentSvgDetails.arrayCount} Array{currentSvgDetails.arrayCount > 1 ? 's' : ''}
                              </span>
                            </div>
                          </div>
                          
                          {/* Step Progress Dots */}
                          <div className="flex items-center gap-1">
                            {svgSteps.map((_, idx) => (
                              <div 
                                key={idx}
                                className="w-2 h-2 rounded-full"
                                style={{ 
                                  backgroundColor: idx < currentStep 
                                    ? colors.success 
                                    : idx === currentStep - 1 
                                      ? colors.warmLed 
                                      : colors.bgElevated
                                }}
                              />
                            ))}
                          </div>
                        </div>
                      </div>
                    );
                  })()}
                </div>
              );
            })}
          </div>
        </div>

        {/* Footer Legend */}
        <div 
          className="mt-6 flex items-center gap-6 text-xs p-4 rounded-lg"
          style={{ 
            backgroundColor: colors.bgCard,
            border: `1px solid ${colors.border}`,
            color: colors.textMuted
          }}
        >
          <span className="font-semibold" style={{ color: colors.textSecondary }}>Group Types:</span>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: colors.electricBlue }}></div>
            <span>Same ID × Full</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: colors.skyBlue }}></div>
            <span>Same ID × Partial</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: colors.warmLed }}></div>
            <span>Mixed ID × Full</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded" style={{ backgroundColor: colors.coolLed }}></div>
            <span>Mixed ID × Partial</span>
          </div>
        </div>

        {/* Brand Footer */}
        <div 
          className="mt-4 text-center text-xs py-2"
          style={{ color: colors.textMuted }}
        >
          LUXEON STAR LEDs by Quadica® • Production Management System
        </div>
      </div>
    </div>
  );
}