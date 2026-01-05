import React, { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, Play, CheckCircle, Loader2, FileCode, Layers, Grid3X3, Clock, RefreshCw, RotateCcw } from 'lucide-react';

/*
 * WordPress Admin Color Scheme
 * Engraving Queue Screen - displayed after clicking "Start Engraving" on Batch Creator
 */

// WordPress Admin color constants
const colors = {
  // Backgrounds
  bgPrimary: '#f0f0f1',
  bgCard: '#ffffff',
  bgElevated: '#f6f7f7',
  bgHover: '#f0f6fc',
  
  // WordPress blues
  wpBlue: '#2271b1',
  wpBlueHover: '#135e96',
  wpBlueFocus: '#043959',
  wpBlueLight: '#f0f6fc',
  
  // Text
  textPrimary: '#1d2327',
  textSecondary: '#50575e',
  textMuted: '#787c82',
  
  // Accents
  success: '#00a32a',
  successLight: '#edfaef',
  warning: '#dba617',
  warningLight: '#fcf9e8',
  error: '#d63638',
  errorLight: '#fcf0f1',
  
  // Borders
  border: '#c3c4c7',
  borderLight: '#dcdcde',
  borderFocus: '#2271b1',
};

// Mock data for the engraving queue
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
    status: 'pending'
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
  const [currentArray, setCurrentArray] = useState({});

  // Keyboard shortcut: Spacebar advances to next array
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.code === 'Space' && activeItemId !== null) {
        e.preventDefault();
        
        const item = queueItems.find(i => i.id === activeItemId);
        if (!item || item.status !== 'in_progress') return;
        
        const offset = startOffsets[activeItemId] !== undefined ? startOffsets[activeItemId] : 1;
        const totalModules = item.totalModules;
        
        let remaining = totalModules;
        let arrayCount = 0;
        if (offset > 1) {
          arrayCount++;
          remaining -= Math.min(remaining, 9 - offset);
        }
        if (remaining > 0) {
          arrayCount += Math.ceil(remaining / 8);
        }
        
        const current = currentArray[activeItemId] || 0;
        const isLastArray = current >= arrayCount;
        
        if (!isLastArray) {
          setCurrentArray(prev => ({ ...prev, [activeItemId]: current + 1 }));
          console.log('Spacebar: Advance to Array', current + 1);
        }
      }
    };
    
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [activeItemId, queueItems, startOffsets, currentArray]);

  // Calculate array breakdown for a row
  const calculateArrayBreakdown = (totalModules, startOffset) => {
    const arrays = [];
    let remainingModules = totalModules;
    let serialStart = 1;
    
    if (startOffset > 1) {
      const firstArrayModules = Math.min(remainingModules, 9 - startOffset);
      arrays.push({
        arrayNum: 1,
        startPos: startOffset,
        endPos: startOffset + firstArrayModules - 1,
        moduleCount: firstArrayModules,
        serialStart: serialStart,
        serialEnd: serialStart + firstArrayModules - 1,
        description: `Positions ${startOffset}-${startOffset + firstArrayModules - 1}`
      });
      serialStart += firstArrayModules;
      remainingModules -= firstArrayModules;
    }
    
    while (remainingModules > 0) {
      const arrayModules = Math.min(remainingModules, 8);
      const endPos = arrayModules;
      arrays.push({
        arrayNum: arrays.length + 1,
        startPos: 1,
        endPos: endPos,
        moduleCount: arrayModules,
        serialStart: serialStart,
        serialEnd: serialStart + arrayModules - 1,
        description: `Positions 1-${endPos}`
      });
      serialStart += arrayModules;
      remainingModules -= arrayModules;
    }
    
    return arrays;
  };

  const getCurrentArray = (itemId) => {
    return currentArray[itemId] || 0;
  };

  const calculateTotalArrays = (totalModules, startOffset) => {
    let remainingModules = totalModules;
    let arrayCount = 0;
    
    if (startOffset > 1) {
      const firstArrayModules = Math.min(remainingModules, 9 - startOffset);
      arrayCount += 1;
      remainingModules -= firstArrayModules;
    }
    
    if (remainingModules > 0) {
      arrayCount += Math.ceil(remainingModules / 8);
    }
    
    return arrayCount;
  };

  const startEngraving = (itemId) => {
    const item = queueItems.find(i => i.id === itemId);
    const offset = getStartOffset(itemId);
    const arrays = calculateArrayBreakdown(item.totalModules, offset);
    
    setActiveItemId(itemId);
    setCurrentArray(prev => ({ ...prev, [itemId]: 1 }));
    setQueueItems(prev => prev.map(item => 
      item.id === itemId ? { ...item, status: 'in_progress' } : item
    ));
    
    console.log('Pre-generate', arrays.length, 'SVG files for item:', itemId);
  };

  const nextArray = (itemId) => {
    const item = queueItems.find(i => i.id === itemId);
    const offset = getStartOffset(itemId);
    const arrays = calculateArrayBreakdown(item.totalModules, offset);
    const current = getCurrentArray(itemId);
    const next = current + 1;
    
    setCurrentArray(prev => ({ ...prev, [itemId]: next }));
    console.log('Advance to Array', next, 'of', arrays.length);
  };

  const prevArray = (itemId) => {
    const current = getCurrentArray(itemId);
    const prev = Math.max(1, current - 1);
    setCurrentArray(prevState => ({ ...prevState, [itemId]: prev }));
    console.log('Go back to Array', prev);
  };

  const resendCurrentSvg = (itemId) => {
    const current = getCurrentArray(itemId);
    console.log('Resend SVG for Array', current);
  };

  const retryCurrentArray = (itemId) => {
    const current = getCurrentArray(itemId);
    console.log('Retry Array', current, 'with new serials');
  };

  const getStartOffset = (itemId) => {
    return startOffsets[itemId] !== undefined ? startOffsets[itemId] : 1;
  };

  const updateStartOffset = (itemId, value) => {
    const numValue = parseInt(value) || 1;
    const clampedValue = Math.max(1, Math.min(8, numValue));
    setStartOffsets(prev => ({
      ...prev,
      [itemId]: clampedValue
    }));
  };

  const completeEngraving = (itemId) => {
    setQueueItems(prev => prev.map(item => 
      item.id === itemId ? { ...item, status: 'complete' } : item
    ));
    setActiveItemId(null);
    setCurrentArray(prev => ({ ...prev, [itemId]: 0 }));
    console.log('Complete engraving for item:', itemId);
  };

  const rerunEngraving = (itemId) => {
    setActiveItemId(null);
    setCurrentArray(prev => ({ ...prev, [itemId]: 0 }));
    setQueueItems(prev => prev.map(item => 
      item.id === itemId ? { ...item, status: 'pending' } : item
    ));
    console.log('Rerun requested for item:', itemId);
  };

  const totalArrays = queueItems.reduce((sum, item) => 
    sum + calculateTotalArrays(item.totalModules, getStartOffset(item.id)), 0);
  const completedArrays = queueItems
    .filter(item => item.status === 'complete')
    .reduce((sum, item) => sum + calculateTotalArrays(item.totalModules, getStartOffset(item.id)), 0);
  const totalModules = queueItems.reduce((sum, item) => sum + item.totalModules, 0);
  const completedModules = queueItems
    .filter(item => item.status === 'complete')
    .reduce((sum, item) => sum + item.totalModules, 0);

  const getStatusStyle = (status) => {
    switch (status) {
      case 'complete':
        return { bg: colors.successLight, color: colors.success, text: 'Complete' };
      case 'in_progress':
        return { bg: colors.warningLight, color: colors.warning, text: 'In Progress' };
      default:
        return { bg: colors.bgElevated, color: colors.textMuted, text: 'Pending' };
    }
  };

  const getGroupTypeColor = (groupType) => {
    if (groupType.includes('Same ID') && groupType.includes('Full')) return colors.wpBlue;
    if (groupType.includes('Same ID') && groupType.includes('Partial')) return '#0a9fd8';
    if (groupType.includes('Mixed ID') && groupType.includes('Full')) return colors.warning;
    return '#72aee6';
  };

  return (
    <div 
      className="min-h-screen p-6"
      style={{ 
        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif",
        backgroundColor: colors.bgPrimary,
        color: colors.textPrimary
      }}
    >
      <div className="max-w-5xl mx-auto">
        {/* Header */}
        <div 
          className="rounded-t px-6 py-4 flex items-center justify-between"
          style={{ 
            backgroundColor: colors.bgCard,
            borderBottom: `1px solid ${colors.borderLight}`
          }}
        >
          <div className="flex items-center gap-3">
            <button
              onClick={() => console.log('Navigate back to Batch Creator')}
              className="p-2 rounded transition-all"
              style={{ color: colors.textMuted }}
              onMouseOver={(e) => {
                e.currentTarget.style.backgroundColor = colors.bgElevated;
                e.currentTarget.style.color = colors.wpBlue;
              }}
              onMouseOut={(e) => {
                e.currentTarget.style.backgroundColor = 'transparent';
                e.currentTarget.style.color = colors.textMuted;
              }}
              title="Back to Batch Creator"
            >
              <ChevronLeft className="w-6 h-6" />
            </button>
            <div 
              className="w-10 h-10 rounded flex items-center justify-center"
              style={{ backgroundColor: colors.wpBlue }}
            >
              <Grid3X3 className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-xl font-semibold" style={{ color: colors.textPrimary }}>
                Engraving Queue
              </h1>
              <p className="text-sm" style={{ color: colors.textMuted }}>
                Step through arrays for engraving
              </p>
            </div>
          </div>
        </div>

        {/* Progress Stats Bar */}
        <div 
          className="grid grid-cols-4"
          style={{ 
            backgroundColor: colors.bgCard,
            borderLeft: `1px solid ${colors.borderLight}`,
            borderRight: `1px solid ${colors.borderLight}`
          }}
        >
          <div className="p-4 text-center" style={{ borderRight: `1px solid ${colors.borderLight}` }}>
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Queue Items</div>
            <div className="text-2xl font-semibold" style={{ color: colors.textPrimary }}>{queueItems.length}</div>
          </div>
          <div className="p-4 text-center" style={{ borderRight: `1px solid ${colors.borderLight}` }}>
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Arrays</div>
            <div className="text-2xl font-semibold">
              <span style={{ color: colors.success }}>{completedArrays}</span>
              <span style={{ color: colors.textMuted }}> / {totalArrays}</span>
            </div>
          </div>
          <div className="p-4 text-center" style={{ borderRight: `1px solid ${colors.borderLight}` }}>
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Modules</div>
            <div className="text-2xl font-semibold">
              <span style={{ color: colors.success }}>{completedModules}</span>
              <span style={{ color: colors.textMuted }}> / {totalModules}</span>
            </div>
          </div>
          <div className="p-4 text-center">
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Progress</div>
            <div className="text-2xl font-semibold" style={{ color: colors.wpBlue }}>
              {totalArrays > 0 ? Math.round((completedArrays / totalArrays) * 100) : 0}%
            </div>
          </div>
        </div>

        {/* Progress Bar */}
        <div 
          style={{ 
            backgroundColor: colors.bgCard,
            borderLeft: `1px solid ${colors.borderLight}`,
            borderRight: `1px solid ${colors.borderLight}`,
            padding: '0 16px 16px 16px'
          }}
        >
          <div 
            className="h-2 rounded-full overflow-hidden"
            style={{ backgroundColor: colors.bgElevated }}
          >
            <div 
              className="h-full rounded-full transition-all duration-500"
              style={{ 
                width: `${totalArrays > 0 ? (completedArrays / totalArrays) * 100 : 0}%`,
                backgroundColor: colors.success
              }}
            />
          </div>
        </div>

        {/* Queue List */}
        <div 
          className="rounded-b overflow-hidden"
          style={{ border: `1px solid ${colors.borderLight}`, borderTop: 'none' }}
        >
          <div 
            className="px-4 py-2 flex items-center gap-2"
            style={{ backgroundColor: colors.bgElevated, borderBottom: `1px solid ${colors.borderLight}` }}
          >
            <Layers className="w-4 h-4" style={{ color: colors.wpBlue }} />
            <span className="text-sm font-medium" style={{ color: colors.textSecondary }}>
              Array Engraving Queue
            </span>
          </div>

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
                    borderBottom: isLast ? 'none' : `1px solid ${colors.borderLight}`,
                    backgroundColor: item.status === 'in_progress' ? colors.warningLight : colors.bgCard
                  }}
                >
                  {/* Row Header */}
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-3">
                      <div 
                        className="w-8 h-8 rounded-full flex items-center justify-center"
                        style={{ backgroundColor: statusStyle.bg }}
                      >
                        {item.status === 'complete' && <CheckCircle className="w-4 h-4" style={{ color: statusStyle.color }} />}
                        {item.status === 'in_progress' && <Loader2 className="w-4 h-4 animate-spin" style={{ color: statusStyle.color }} />}
                        {item.status === 'pending' && <Clock className="w-4 h-4" style={{ color: statusStyle.color }} />}
                      </div>
                      
                      <span 
                        className="px-3 py-1 rounded text-sm font-bold"
                        style={{ 
                          fontFamily: "'SF Mono', 'Consolas', monospace",
                          backgroundColor: colors.wpBlueLight,
                          color: colors.wpBlue,
                          border: `1px solid ${colors.wpBlue}30`
                        }}
                      >
                        {item.moduleType}
                      </span>
                      
                      <span 
                        className="px-2 py-1 rounded text-xs"
                        style={{ backgroundColor: `${groupTypeColor}20`, color: groupTypeColor }}
                      >
                        {item.groupType}
                      </span>
                      
                      <span 
                        className="px-2 py-1 rounded text-xs"
                        style={{ backgroundColor: statusStyle.bg, color: statusStyle.color }}
                      >
                        {statusStyle.text}
                      </span>
                    </div>
                    
                    {/* Action Buttons */}
                    <div className="flex items-center gap-2">
                      {item.status === 'pending' && (
                        <button
                          onClick={() => startEngraving(item.id)}
                          className="px-4 py-2 rounded font-medium text-sm flex items-center gap-2 transition-all"
                          style={{ backgroundColor: colors.wpBlue, color: '#FFFFFF' }}
                          onMouseOver={(e) => e.currentTarget.style.backgroundColor = colors.wpBlueHover}
                          onMouseOut={(e) => e.currentTarget.style.backgroundColor = colors.wpBlue}
                        >
                          <Play className="w-4 h-4" />
                          Engrave
                        </button>
                      )}
                      {item.status === 'in_progress' && (() => {
                        const arrays = calculateArrayBreakdown(item.totalModules, getStartOffset(item.id));
                        const currentArrayNum = getCurrentArray(item.id);
                        const isLastArray = currentArrayNum >= arrays.length;
                        const isFirstArray = currentArrayNum <= 1;
                        
                        return (
                          <div className="flex items-center gap-2">
                            {!isFirstArray && (
                              <button
                                onClick={() => prevArray(item.id)}
                                className="px-3 py-2 rounded text-sm flex items-center gap-1 transition-all"
                                style={{ backgroundColor: colors.bgElevated, color: colors.textSecondary, border: `1px solid ${colors.border}` }}
                                onMouseOver={(e) => { e.currentTarget.style.backgroundColor = colors.bgHover; e.currentTarget.style.borderColor = colors.wpBlue; }}
                                onMouseOut={(e) => { e.currentTarget.style.backgroundColor = colors.bgElevated; e.currentTarget.style.borderColor = colors.border; }}
                              >
                                <ChevronLeft className="w-4 h-4" />
                                Back
                              </button>
                            )}
                            
                            <button
                              onClick={() => resendCurrentSvg(item.id)}
                              className="px-3 py-2 rounded text-sm flex items-center gap-1 transition-all"
                              style={{ backgroundColor: colors.bgCard, color: colors.wpBlue, border: `1px solid ${colors.wpBlue}` }}
                              onMouseOver={(e) => e.currentTarget.style.backgroundColor = colors.wpBlueLight}
                              onMouseOut={(e) => e.currentTarget.style.backgroundColor = colors.bgCard}
                            >
                              <RefreshCw className="w-4 h-4" />
                              Resend
                            </button>
                            
                            <button
                              onClick={() => retryCurrentArray(item.id)}
                              className="px-3 py-2 rounded text-sm flex items-center gap-1 transition-all"
                              style={{ backgroundColor: colors.bgCard, color: colors.warning, border: `1px solid ${colors.warning}` }}
                              onMouseOver={(e) => e.currentTarget.style.backgroundColor = colors.warningLight}
                              onMouseOut={(e) => e.currentTarget.style.backgroundColor = colors.bgCard}
                            >
                              <RotateCcw className="w-4 h-4" />
                              Retry
                            </button>
                            
                            {isLastArray ? (
                              <button
                                onClick={() => completeEngraving(item.id)}
                                className="px-4 py-2 rounded font-medium text-sm flex items-center gap-2 transition-all"
                                style={{ backgroundColor: colors.success, color: '#FFFFFF' }}
                                onMouseOver={(e) => e.currentTarget.style.backgroundColor = '#008a20'}
                                onMouseOut={(e) => e.currentTarget.style.backgroundColor = colors.success}
                              >
                                <CheckCircle className="w-4 h-4" />
                                Complete
                              </button>
                            ) : (
                              <button
                                onClick={() => nextArray(item.id)}
                                className="px-4 py-2 rounded font-medium text-sm flex items-center gap-2 transition-all"
                                style={{ backgroundColor: colors.warning, color: '#FFFFFF' }}
                                onMouseOver={(e) => e.currentTarget.style.backgroundColor = '#c59215'}
                                onMouseOut={(e) => e.currentTarget.style.backgroundColor = colors.warning}
                                title="Press SPACEBAR or click"
                              >
                                <ChevronRight className="w-4 h-4" />
                                Next Array
                              </button>
                            )}
                          </div>
                        );
                      })()}
                      {item.status === 'complete' && (
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => rerunEngraving(item.id)}
                            className="px-3 py-2 rounded text-sm flex items-center gap-1 transition-all"
                            style={{ backgroundColor: colors.bgElevated, color: colors.textSecondary, border: `1px solid ${colors.border}` }}
                            onMouseOver={(e) => { e.currentTarget.style.backgroundColor = colors.bgHover; }}
                            onMouseOut={(e) => { e.currentTarget.style.backgroundColor = colors.bgElevated; }}
                          >
                            <RefreshCw className="w-4 h-4" />
                            Rerun
                          </button>
                          <span 
                            className="px-4 py-2 rounded text-sm flex items-center gap-2"
                            style={{ backgroundColor: colors.successLight, color: colors.success }}
                          >
                            <CheckCircle className="w-4 h-4" />
                            Done
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                  
                  {/* Row Details */}
                  <div className="flex items-center gap-6 ml-11 text-sm" style={{ color: colors.textSecondary }}>
                    <div className="flex items-center gap-2">
                      <FileCode className="w-4 h-4" style={{ color: colors.textMuted }} />
                      <span style={{ color: colors.textMuted }}>Modules:</span>
                      <div className="flex items-center gap-2">
                        {item.modules.map((mod, i) => (
                          <span 
                            key={i}
                            className="px-2 py-0.5 rounded text-xs"
                            style={{ fontFamily: "'SF Mono', 'Consolas', monospace", backgroundColor: colors.bgElevated, color: colors.textSecondary }}
                          >
                            {mod.sku} ×{mod.qty}
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                  
                  {/* Stats Row */}
                  <div className="flex items-center gap-6 ml-11 mt-2 text-sm">
                    <div className="flex items-center gap-2">
                      <span style={{ color: colors.textMuted }}>Arrays:</span>
                      <span className="font-medium" style={{ color: colors.textPrimary }}>
                        {calculateTotalArrays(item.totalModules, getStartOffset(item.id))}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span style={{ color: colors.textMuted }}>Total Modules:</span>
                      <span className="font-medium" style={{ color: colors.textPrimary }}>{item.totalModules}</span>
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
                        className="w-12 px-2 py-0.5 rounded text-center text-sm font-medium"
                        style={{
                          backgroundColor: item.status === 'pending' ? colors.bgCard : colors.bgElevated,
                          color: item.status === 'pending' ? colors.textPrimary : colors.textMuted,
                          border: `1px solid ${item.status === 'pending' ? colors.border : colors.borderLight}`,
                          outline: 'none',
                          opacity: item.status === 'pending' ? 1 : 0.6
                        }}
                        onFocus={(e) => {
                          if (item.status === 'pending') {
                            e.target.style.borderColor = colors.wpBlue;
                            e.target.style.boxShadow = `0 0 0 1px ${colors.wpBlue}`;
                          }
                        }}
                        onBlur={(e) => {
                          e.target.style.borderColor = colors.border;
                          e.target.style.boxShadow = 'none';
                        }}
                      />
                    </div>
                  </div>
                  
                  {/* Current Array Details */}
                  {item.status === 'in_progress' && (() => {
                    const arrays = calculateArrayBreakdown(item.totalModules, getStartOffset(item.id));
                    const currentArrayNum = getCurrentArray(item.id);
                    const currentArrayDetails = arrays[currentArrayNum - 1];
                    
                    if (!currentArrayDetails) return null;
                    
                    const formatSerial = (num) => String(num).padStart(8, '0');
                    
                    return (
                      <div 
                        className="ml-11 mt-3 p-3 rounded"
                        style={{ backgroundColor: colors.warningLight, border: `1px solid ${colors.warning}30` }}
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div className="flex items-center gap-4">
                            <div 
                              className="flex items-center gap-2 px-3 py-1 rounded-full"
                              style={{ backgroundColor: colors.warning, color: '#FFFFFF' }}
                            >
                              <Grid3X3 className="w-4 h-4" />
                              <span className="font-bold text-sm">
                                Array {currentArrayNum} of {arrays.length}
                              </span>
                            </div>
                            
                            <div className="flex items-center gap-2">
                              <span style={{ color: colors.textMuted }}>Positions:</span>
                              <span className="font-medium px-2 py-0.5 rounded" style={{ backgroundColor: colors.bgCard, color: colors.textPrimary }}>
                                {currentArrayDetails.startPos} - {currentArrayDetails.endPos}
                              </span>
                            </div>
                            
                            <div className="flex items-center gap-2">
                              <span style={{ color: colors.textMuted }}>Modules:</span>
                              <span className="font-medium" style={{ color: colors.textPrimary }}>{currentArrayDetails.moduleCount}</span>
                            </div>
                            
                            <div className="flex items-center gap-2">
                              <span style={{ color: colors.textMuted }}>Serials:</span>
                              <span className="font-mono text-xs px-2 py-0.5 rounded" style={{ backgroundColor: colors.bgCard, color: colors.wpBlue }}>
                                {formatSerial(currentArrayDetails.serialStart)} - {formatSerial(currentArrayDetails.serialEnd)}
                              </span>
                            </div>
                          </div>
                          
                          <div className="flex items-center gap-1">
                            {arrays.map((_, idx) => (
                              <div 
                                key={idx}
                                className="w-2 h-2 rounded-full"
                                style={{ backgroundColor: idx < currentArrayNum ? colors.success : idx === currentArrayNum - 1 ? colors.warning : colors.borderLight }}
                              />
                            ))}
                          </div>
                        </div>
                        
                        {currentArrayNum < arrays.length && (
                          <div className="flex items-center gap-2 text-xs mt-2 pt-2" style={{ borderTop: `1px solid ${colors.warning}20`, color: colors.textMuted }}>
                            <span className="px-2 py-0.5 rounded font-mono" style={{ backgroundColor: colors.bgCard, color: colors.textSecondary, border: `1px solid ${colors.border}` }}>
                              SPACEBAR
                            </span>
                            <span>Press spacebar or click Next Array to advance</span>
                          </div>
                        )}
                      </div>
                    );
                  })()}
                </div>
              );
            })}
          </div>
        </div>

        {/* Footer */}
        <div className="mt-4 text-center text-xs py-2" style={{ color: colors.textMuted }}>
          QSA Engraving System • Quadica Developments Inc.
        </div>
      </div>
    </div>
  );
}
